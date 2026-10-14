/*
 * AquaSense device firmware (reference sketch for the Wokwi simulation).
 *
 * Target:      ESP32-S3 (Wokwi part "wokwi-esp32-s3-devkitc-1")
 * Sensor:      HC-SR04 ultrasonic (Wokwi part "wokwi-hc-sr04")
 * Wiring:      HC-SR04 VCC  -> ESP32-S3 3V3
 *              HC-SR04 GND  -> ESP32-S3 GND
 *              HC-SR04 TRIG -> ESP32-S3 GPIO 5
 *              HC-SR04 ECHO -> ESP32-S3 GPIO 6
 *              (see ../wokwi/diagram.json)
 *
 * Behaviour
 * ---------
 * 1. Take several ultrasonic samples and discard non-zero outliers, then
 *    average the rest. A sensor timeout returns 0, which means "no echo".
 * 2. Send ONLY the raw distance in centimetres to the AquaSense API. The device
 *    never computes litres: water height, percentage and volume are derived
 *    server-side from the tank geometry, so a firmware change is never needed
 *    when a tank is reconfigured.
 * 3. Authenticate with a per-device bearer token.
 * 4. Deep sleep until the next reporting interval to save battery.
 *
 * NOTE ON THE SENSOR
 * The HC-SR04 is used here because it is the part Wokwi simulates. It is NOT
 * waterproof and is NOT the intended physical installation part. A real tank
 * installation needs a waterproof probe such as the JSN-SR04T (or a sealed
 * VL53L1X above a dry headspace) - see docs/HARDWARE_COMPONENTS.md.
 *
 * The ECHO pin of a real HC-SR04 outputs 5 V. On a physical ESP32-S3 (3.3 V
 * logic, not 5 V tolerant) you must use a level shifter or a resistive divider.
 * Wokwi does not model this, which is exactly why the sketch is a simulation
 * reference and not a build-ready production image.
 *
 * Credentials come from build flags so no token is committed:
 *   arduino-cli compile -e --build-property \
 *     "compiler.cpp.extra_flags=-DAQUASENSE_SSID=\\\"net\\\" \
 *      -DAQUASENSE_TOKEN=\\\"device-token\\\""
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

#ifndef AQUASENSE_SSID
#define AQUASENSE_SSID "Wokwi-GUEST"
#endif
#ifndef AQUASENSE_PASSWORD
#define AQUASENSE_PASSWORD ""
#endif
#ifndef AQUASENSE_TOKEN
// Local simulation placeholder only. A real device token is long and random.
#define AQUASENSE_TOKEN "demo-device-token-0001"
#endif

// Wokwi's virtual network has no path to your laptop by default. The free
// browser playground uses the Public Gateway: outbound internet only, no route to
// 127.0.0.1. Reaching your machine needs the paid Private IoT Gateway, which is
// what makes host.wokwi.internal resolve. See docs/SIMULATION.md. Until then this
// sketch targets the deployed AquaSense endpoint - or, for a fully local proof of
// the same payload, use simulation/software/simulate.php.
#ifndef AQUASENSE_ENDPOINT
#define AQUASENSE_ENDPOINT "https://api.aquasense.example/api/v1/telemetry"
#endif

// --- hardware ---------------------------------------------------------------
static const uint8_t PIN_TRIG = 5;
static const uint8_t PIN_ECHO = 6;

// --- reporting --------------------------------------------------------------
#define uS_TO_S_FACTOR 1000000ULL
#define TIME_TO_SLEEP_SECONDS 300
#define SAMPLE_COUNT 10

// Root CA for the API endpoint. Wokwi ships the ISRG Root X1 bundle used by
// Let's Encrypt; replace this with your own CA for a production deployment.
static const char *ROOT_CA PROGMEM = R"CERT(
-----BEGIN CERTIFICATE-----
MIIFazCCA1OgAwIBAgIRAIIQz7DSQONZRGPgu2OCiwAwDQYJKoZIhvcNAQELBQAw
TzELMAkGA1UEBhMCVVMxKTAnBgNVBAoTIEludGVybmV0IFNlY3VyaXR5IFJlc2Vh
cmNoIEdyb3VwMRUwEwYDVQQDEwxJU1JHIFJvb3QgWDEwHhcNMTUwNjA0MTEwNDM4
WhcNMzUwNjA0MTEwNDM4WjBPMQswCQYDVQQGEwJVUzEpMCcGA1UEChMgSW50ZXJu
ZXQgU2VjdXJpdHkgUmVzZWFyY2ggR3JvdXAxFTATBgNVBAMTDElTUkcgUm9vdCBY
MTCCAiIwDQYJKoZIhvcNAQEBBQADggIPADCCAgoCggIBAK3oJHP0FDfzm54rVygc
h77ct984kIxuPOZXoHj3dcKi/vVqbvYATyjb3miGbESTtrFj/RQSa78f0uoxmyF+
0TM8ukj13Xnfs7j/EvEhmkvBioZxaUpmZmyPfjxwv60pIgbz5MDmgK7iS4+3mX6U
A5/TR5d8mUgjU+g4rk8Kb4Mu0UlXjIB0ttov0DiNewNwIRt18jA8+o+u3dpjq+sW
T8KOEUt+zwvo/7V3LvSye0rgTBIlDHCNAymg4VMk7BPZ7hm/ELNKjD+Jo2FR3qyH
B5T0Y3HsLuJvW5iB4YlcNHlsdu87kGJ55tukmi8mxdAQ4Q7e2RCOFvu396j3x+UC
B5iPNgiV5+I3lg02dZ77DnKxHZu8A/lJBdiB3QW0KtZB6awBdpUKD9jf1b0SHzUv
KBds0pjBqAlkd25HN7rOrFleaJ1/ctaJxQZBKT5ZPt0m9STJEadao0xAH0ahmbWn
OlFuhjuefXKnEgV4We0+UXgVCwOPjdAvBbI+e0ocS3MFEvzG6uBQE3xDk3SzynTn
jh8BCNAw1FtxNrQHusEwMFxIt4I7mKZ9YIqioymCzLq9gwQbooMDQaHWBfEbwrbw
qHyGO0aoSCqI3Haadr8faqU9GY/rOPNk3sgrDQoo//fb4hVC1CLQJ13hef4Y53CI
rU7m2Ys6xt0nUW7/vGT1M0NPAgMBAAGjQjBAMA4GA1UdDwEB/wQEAwIBBjAPBgNV
HRMBAf8EBTADAQH/MB0GA1UdDgQWBBR5tFnme7bl5AFzgAiIyBpY9umbbjANBgkq
hkiG9w0BAQsFAAOCAgEAVR9YqbyyqFDQDLHYGmkgJykIrGF1XIpu+ILlaS/V9lZL
ubhzEFnTIZd+50xx+7LSYK05qAvqFyFWhfFQDlnrzuBZ6brJFe+GnY+EgPbk6ZGQ
3BebYhtF8GaV0nxvwuo77x/Py9auJ/GpsMiu/X1+mvoiBOv/2X/qkSsisRcOj/KK
NFtY2PwByVS5uCbMiogziUwthDyC3+6WVwW6LLv3xLfHTjuCvjHIInNzktHCgKQ5
ORAzI4JMPJ+GslWYHb4phowim57iaztXOoJwTdwJx4nLCgdNbOhjdsnvzqvHu7Ur
TkXWStAmzOVyyghqpZXjFaH3pO3JLF+l+/+sKAIuvtd7u+Nxe5AW0wdeRlN8NwdC
jNPElpzVmbUq4JUagEiuTDkHzsxHpFKVK7q4+63SM1N95R1NbdWhscdCb+ZAJzVc
oyi3B43njTOQ5yOf+1CceWxG1bQVs5ZufpsMljq4Ui0/1lvh+wjChP4kqKOJ2qxq
4RgqsahDYVvTH9w7jXbyLeiNdd8XM2w9U/t7y0Ff/9yi0GE44Za4rF2LN9d11TPA
mRGunUHBcnWEvgJBQl9nJEiU0Zsnvgc/ubhPgXRR4Xq37Z0j4r7g1SgEEzwxA57d
emyPxgcYxn/eR44/KJ4EBs+lVDR3veyJm+kXQ99b21/+jh5Xos1AnX5iItreGCc=
-----END CERTIFICATE-----
)CERT";

#ifndef AQUASENSE_SERIAL
#define AQUASENSE_SERIAL "AQS-S3-DEMO"
#endif
#ifndef AQUASENSE_FIRMWARE
#define AQUASENSE_FIRMWARE "aquasense-wokwi/1.0.0"
#endif

RTC_DATA_ATTR unsigned long bootCount = 0;

/**
 * One ultrasonic measurement in centimetres.
 *
 * Returns 0 when no echo arrives inside the timeout. Zero means "no reading",
 * NOT "empty tank" - the API interprets it exactly the same way.
 */
static unsigned int measureDistanceCm()
{
  digitalWrite(PIN_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(PIN_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_TRIG, LOW);

  // 30 ms timeout caps the usable range near 5 m; the HC-SR04 is rated to ~4 m.
  unsigned long echoMicros = pulseIn(PIN_ECHO, HIGH, 30000UL);
  if (echoMicros == 0UL) {
    return 0;
  }

  return (unsigned int)(echoMicros / 58UL);
}

/**
 * Average several samples, rejecting outliers.
 *
 * The smallest and largest readings are discarded, then the rest are averaged,
 * so a single bad echo cannot move the reported level.
 */
static unsigned int measureAveragedCm()
{
  unsigned int samples[SAMPLE_COUNT];
  unsigned int valid = 0;

  for (int i = 0; i < SAMPLE_COUNT; i++) {
    unsigned int reading = measureDistanceCm();
    if (reading > 0) {
      samples[valid++] = reading;
    }
    delay(60);
  }

  if (valid == 0) {
    return 0; // every ping timed out
  }

  if (valid <= 2) {
    unsigned long total = 0;
    for (unsigned int i = 0; i < valid; i++) {
      total += samples[i];
    }
    return (unsigned int)(total / valid);
  }

  unsigned int minIndex = 0;
  unsigned int maxIndex = 0;
  for (unsigned int i = 1; i < valid; i++) {
    if (samples[i] < samples[minIndex]) minIndex = i;
    if (samples[i] > samples[maxIndex]) maxIndex = i;
  }
  samples[minIndex] = 0;
  samples[maxIndex] = 0;

  unsigned long total = 0;
  unsigned int counted = 0;
  for (unsigned int i = 0; i < valid; i++) {
    if (samples[i] == 0) continue;
    total += samples[i];
    counted++;
  }

  return counted == 0 ? 0 : (unsigned int)(total / counted);
}

/** ISO-8601 UTC timestamp, empty when the device clock is not set yet. */
static String isoTimestampNow()
{
  struct tm timeinfo;
  if (!getLocalTime(&timeinfo, 5000)) {
    // The server falls back to its own receipt time when measured_at is absent.
    return String("");
  }
  char buffer[25];
  strftime(buffer, sizeof(buffer), "%Y-%m-%dT%H:%M:%SZ", &timeinfo);
  return String(buffer);
}

/**
 * POST the raw distance to the AquaSense telemetry endpoint.
 *
 * The payload matches docs/API.md exactly, so this firmware and the software
 * simulator are interchangeable from the server's point of view.
 */
static bool sendTelemetry(unsigned int distanceCm)
{
  WiFiClientSecure client;
  client.setCACert(ROOT_CA);

  HTTPClient https;
  if (!https.begin(client, AQUASENSE_ENDPOINT)) {
    Serial.println(F("[telemetry] begin() failed"));
    return false;
  }

  https.addHeader("Content-Type", "application/json");
  https.setTimeout(15000);

  String payload = String("{\"serial_number\":\"") + AQUASENSE_SERIAL +
                   "\",\"device_token\":\"" + AQUASENSE_TOKEN +
                   "\",\"distance_cm\":" + String(distanceCm) +
                   ",\"source\":\"hardware\"" +
                   ",\"firmware_version\":\"" + AQUASENSE_FIRMWARE +
                   "\",\"measured_at\":\"" + isoTimestampNow() + "\"}";

  Serial.printf("[telemetry] POST %s\n", payload.c_str());

  int status = https.POST(payload);
  Serial.printf("[telemetry] HTTP %d\n", status);
  if (status > 0) {
    Serial.printf("[telemetry] response: %s\n", https.getString().c_str());
  } else {
    Serial.printf("[telemetry] error: %s\n", https.errorToString(status).c_str());
  }

  https.end();
  client.stop();

  return status == 202;
}

void setup()
{
  Serial.begin(115200);
  delay(200);

  pinMode(PIN_TRIG, OUTPUT);
  pinMode(PIN_ECHO, INPUT);
  digitalWrite(PIN_TRIG, LOW);

  bootCount++;
  Serial.printf("=== AquaSense device | boot #%lu ===\n", bootCount);

  unsigned int distanceCm = measureAveragedCm();
  Serial.printf("[sensor] distance to surface: %u cm%s\n",
                distanceCm,
                distanceCm == 0 ? " (no echo)" : "");

  // Wi-Fi is started only after measuring, so the radio - the dominant drain
  // on a battery node - is powered for the shortest possible time.
  WiFi.begin(AQUASENSE_SSID, AQUASENSE_PASSWORD);
  unsigned long startedAt = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - startedAt < 20000UL) {
    delay(250);
    Serial.print('.');
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[wifi] connected, IP %s\n", WiFi.localIP().toString().c_str());
    sendTelemetry(distanceCm);
    WiFi.disconnect(true);
    WiFi.mode(WIFI_OFF);
  } else {
    // Never fabricate a reading. The server notices the gap and marks the
    // device offline instead of showing a stale value as live.
    Serial.println(F("[wifi] not connected - reading discarded, device will look offline"));
  }

  Serial.printf("[sleep] deep sleep for %d s\n", TIME_TO_SLEEP_SECONDS);
  Serial.flush();

  esp_sleep_enable_timer_wakeup((uint64_t)TIME_TO_SLEEP_SECONDS * uS_TO_S_FACTOR);
  esp_deep_sleep_start();
}

void loop()
{
  // Everything happens in setup(); the device sleeps between reports.
}
