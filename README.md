# AquaSense IoT (`aquasense-iot`)

Smart-water-management and resource-monitoring system designed for efficient tracking of water networks, municipal zones, agricultural troughs, and storage reservoirs using affordable, budget-friendly IoT hardware.

## Overview

AquaSense is an open-source IoT solution built to detect drinking-water leaks at an early stage and monitor critical resource levels in real-time. By integrating low-cost microcontrollers with modular sensors, the system automates resource replenishment (such as refilling low tanks or troughs), calculates water loss, and provides instant alerts via a centralized web dashboard.

---

## Features

- **Real-Time Level & Flow Monitoring:** Tracks pipe flow rates and reservoir/trough water levels simultaneously.
- **Automated Refilling / Control:** Triggers relay-driven pumps or valves automatically when resource levels drop below predefined thresholds.
- **Leak Detection & Alerts:** Identifies anomalies in water consumption and notifies users immediately.
- **Interactive Mapping & Dashboard:** Visualizes network status, zone conditions, and telemetry data via a web interface.
- **Budget-Friendly Architecture:** Built around accessible microcontrollers like the ESP32-S3-WROOM-1.

---

## Hardware Requirements

- **Microcontroller:** ESP32-S3-WROOM-1
- **Flow Sensor:** YF-S201 Hall-effect water flow meter
- **Level Sensor:** HC-SR04 ultrasonic distance sensor (or hydrostatic pressure sensor for enclosed pipes)
- **Actuation:** 5V/12V Relay module + miniature water pump or solenoid valve
- **Power Source:** 5V USB power supply or 12V power supply with a step-down buck converter

---

## Repository Structure

```text
aquasense-iot/
├── firmware/            # ESP32 C++ (Arduino/MicroPython) source code
├── backend/             # Node.js / Laravel API server for telemetry ingestion
├── dashboard/           # React / Laravel front-end interface and map views
├── database/            # SQL migration scripts (PostgreSQL / MySQL schemas)
└── docs/                # Wiring diagrams, architecture schematics, and setup guides
