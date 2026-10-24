/*
  ============================================================
  ESP32 BMI Monitor — Arduino Sketch
  ============================================================
  Hardware Required:
    - ESP32 development board
    - HC-SR04 Ultrasonic sensor (measures HEIGHT)
    - HX711 Load Cell amplifier + load cell (measures WEIGHT)
    - Optional: 16x2 LCD with I2C for local display

  Wiring:
    HC-SR04:
      VCC  → 5V
      GND  → GND
      TRIG → GPIO 5
      ECHO → GPIO 18

    HX711:
      VCC  → 3.3V
      GND  → GND
      DT   → GPIO 21
      SCK  → GPIO 22

  Libraries required (install via Arduino Library Manager):
    - HX711 by Bogdan Necula
    - ArduinoJson by Benoit Blanchon
    - (Optional) LiquidCrystal_I2C for LCD display

  Steps:
  1. Install ESP32 board package in Arduino IDE
  2. Install the libraries listed above
  3. Update WIFI_SSID, WIFI_PASS, SERVER_URL below
  4. Calibrate the HX711 scale factor for your load cell
  5. Upload to ESP32
  ============================================================
*/

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include "HX711.h"

// ---- Wi-Fi credentials (CHANGE THESE) ----
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";

// ---- Server API endpoint (CHANGE IP to your PC's local IP) ----
// On XAMPP your PC's local IP is usually 192.168.x.x
// Find it with: ipconfig (Windows) or ifconfig (Linux/Mac)
const char* SERVER_URL = "http://192.168.1.100/bmi_monitor/api/bmi.php";

// ---- User ID to associate this device with (from your database) ----
const int USER_ID = 2;

// ---- HC-SR04 Ultrasonic sensor pins ----
const int TRIG_PIN  = 5;
const int ECHO_PIN  = 18;

// ---- HX711 Load Cell pins ----
const int HX711_DT  = 21;
const int HX711_SCK = 22;

// ---- Load Cell calibration factor ----
// You need to calibrate this for your specific load cell.
// Weigh a known object and adjust until readings match.
const float CALIBRATION_FACTOR = -7050.0;

// ---- Fixed mounting height for the ultrasonic sensor (in cm) ----
// The sensor is mounted at this height above the ground.
// Height of person = SENSOR_HEIGHT - measured_distance
const float SENSOR_HEIGHT_CM = 200.0;

// ---- How often to take a reading (milliseconds) ----
const unsigned long READ_INTERVAL = 10000; // 10 seconds

// ---- Global objects ----
HX711 scale;
unsigned long lastReadTime = 0;

// ---- One-shot flag: set to true when the user types "email" in the
//      Serial Monitor. It gets sent along with the NEXT reading, then
//      automatically resets to false so later readings stay silent. ----
bool sendEmailFlag = false;

// ============================================================
void setup() {
    Serial.begin(115200);
    delay(500);
    Serial.println("\n=== BMI Monitor ESP32 Starting ===");

    // Set up ultrasonic sensor pins
    pinMode(TRIG_PIN, OUTPUT);
    pinMode(ECHO_PIN, INPUT);

    // Set up HX711 load cell
    scale.begin(HX711_DT, HX711_SCK);
    scale.set_scale(CALIBRATION_FACTOR);
    scale.tare();   // Reset scale to zero (remove any weight first)
    Serial.println("Load cell ready. Remove all weight and wait...");
    delay(2000);
    scale.tare();   // Tare again after settling

    // Connect to Wi-Fi
    connectWiFi();

    Serial.println("\nType  email  then press Enter at any time to flag");
    Serial.println("the NEXT reading to be emailed automatically.\n");
}

// ============================================================
void loop() {
    unsigned long now = millis();

    // ---- Listen for Serial commands (non-blocking) ----
    checkSerialCommand();

    // Only read every READ_INTERVAL milliseconds
    if (now - lastReadTime >= READ_INTERVAL) {
        lastReadTime = now;

        // Ensure Wi-Fi is still connected
        if (WiFi.status() != WL_CONNECTED) {
            Serial.println("Wi-Fi lost. Reconnecting...");
            connectWiFi();
        }

        // --- Take measurements ---
        float height = measureHeight();
        float weight = measureWeight();

        // Sanity check: only send valid readings
        if (height < 50 || height > 250) {
            Serial.println("Height reading out of range. Skipping.");
            return;
        }
        if (weight < 1 || weight > 300) {
            Serial.println("Weight reading out of range. Skipping.");
            return;
        }

        // Calculate BMI
        float heightM = height / 100.0;
        float bmi     = weight / (heightM * heightM);
        bmi = roundf(bmi * 100) / 100; // round to 2 decimal places

        Serial.printf("Height: %.1f cm | Weight: %.1f kg | BMI: %.2f\n",
                      height, weight, bmi);

        // Send to server (includes sendEmailFlag if it was set via Serial)
        sendToServer(height, weight, bmi);

        // Reset the one-shot flag after this reading — regardless of whether
        // the HTTP call succeeded, so a failed send doesn't silently retry
        // on every future reading and spam the inbox.
        sendEmailFlag = false;
    }
}

// ============================================================
// Check the Serial port for a typed command (non-blocking).
// Typing "email" + Enter in the Arduino IDE Serial Monitor sets
// a one-shot flag that gets attached to the NEXT reading only.
// ============================================================
void checkSerialCommand() {
    if (!Serial.available()) return;

    String cmd = Serial.readStringUntil('\n');
    cmd.trim();
    cmd.toLowerCase();

    if (cmd == "email") {
        sendEmailFlag = true;
        Serial.println("📧 Next reading will be emailed automatically.");
    }
}

// ============================================================
// Measure height using HC-SR04 ultrasonic sensor
// Returns height of person in centimetres
// ============================================================
float measureHeight() {
    // Send 10µs pulse to trigger
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    // Read echo duration (microseconds)
    long duration = pulseIn(ECHO_PIN, HIGH, 30000); // 30ms timeout

    if (duration == 0) {
        Serial.println("Ultrasonic sensor timeout.");
        return 0;
    }

    // Distance = (duration * speed of sound) / 2
    // Speed of sound ≈ 0.0343 cm/µs
    float distance = (duration * 0.0343) / 2.0;

    // Person's height = sensor height - distance to top of head
    float personHeight = SENSOR_HEIGHT_CM - distance;

    return personHeight;
}

// ============================================================
// Measure weight using HX711 load cell
// Returns weight in kilograms
// ============================================================
float measureWeight() {
    if (!scale.is_ready()) {
        Serial.println("HX711 not ready.");
        return 0;
    }

    // Average 10 readings for stability
    float weight_raw = scale.get_units(10);

    // Convert to kg (calibration factor handles this)
    // Ensure no negative readings
    if (weight_raw < 0) weight_raw = 0;

    return weight_raw;
}

// ============================================================
// Send BMI data to the PHP server via HTTP POST
// ============================================================
void sendToServer(float height, float weight, float bmi) {
    HTTPClient http;
    http.begin(SERVER_URL);
    http.addHeader("Content-Type", "application/json");
    http.setTimeout(5000); // 5 second timeout

    // Build JSON payload
    // Uses ArduinoJson for safe JSON serialisation
    StaticJsonDocument<256> doc;
    doc["user_id"]    = USER_ID;
    doc["height"]     = serialized(String(height, 1));
    doc["weight"]     = serialized(String(weight, 1));
    doc["bmi"]        = serialized(String(bmi,    2));
    doc["send_email"] = sendEmailFlag;   // true only if "email" was typed in Serial Monitor

    String body;
    serializeJson(doc, body);

    Serial.println("Sending to server: " + body);

    int httpCode = http.POST(body);

    if (httpCode == HTTP_CODE_CREATED || httpCode == HTTP_CODE_OK) {
        String response = http.getString();
        Serial.println("Server response: " + response);
        Serial.println("✅ Data sent successfully!");
    } else if (httpCode < 0) {
        Serial.printf("❌ HTTP error: %s\n", http.errorToString(httpCode).c_str());
    } else {
        Serial.printf("❌ Server returned HTTP %d\n", httpCode);
        Serial.println(http.getString());
    }

    http.end();
}

// ============================================================
// Connect to Wi-Fi with retries
// ============================================================
void connectWiFi() {
    Serial.printf("Connecting to Wi-Fi: %s", WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);

    int tries = 0;
    while (WiFi.status() != WL_CONNECTED && tries < 30) {
        delay(500);
        Serial.print(".");
        tries++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n✅ Wi-Fi connected!");
        Serial.print("IP Address: ");
        Serial.println(WiFi.localIP());
    } else {
        Serial.println("\n❌ Wi-Fi connection failed. Will retry in next loop.");
    }
}

/*
  ============================================================
  HOW TO CALIBRATE THE LOAD CELL:
  ============================================================
  1. Upload the sketch with CALIBRATION_FACTOR = 1
  2. Open Serial Monitor at 115200 baud
  3. Note the raw reading with no weight
  4. Place a known weight (e.g. 1 kg bag of sugar)
  5. Note the new raw reading
  6. CALIBRATION_FACTOR = (reading_with_weight - reading_empty) / known_weight_in_kg
  7. Update CALIBRATION_FACTOR and re-upload

  ============================================================
  HOW TO TEST WITHOUT HARDWARE:
  ============================================================
  You can simulate ESP32 data by sending a POST request from
  your terminal (requires curl):

  curl -X POST http://localhost/bmi_monitor/api/bmi.php \
       -H "Content-Type: application/json" \
       -d '{"height":170,"weight":65,"bmi":22.49,"user_id":2}'

  To also test the automated email trigger, add "send_email":true:

  curl -X POST http://localhost/bmi_monitor/api/bmi.php \
       -H "Content-Type: application/json" \
       -d '{"height":170,"weight":65,"bmi":22.49,"user_id":2,"send_email":true}'

  Or use the "Manual Entry" form on the Dashboard page.
  ============================================================
*/
