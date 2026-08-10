#!/usr/bin/env python3
"""
jetson_mqtt_tag_subscriber.py
------------------------------
Runs ON THE JETSON ORIN. Subscribes to the MQTT topic that the FXR90's
Zebra IoT Connector (ZIOTC) publishes tag reads to, and prints/handles
each tag report as it arrives.

Architecture:
    FXR90 (Radio Control + IoT Connector) --MQTT--> Broker --> this script

The broker can be:
  (a) Mosquitto running locally on this same Jetson (simplest -- the
      FXR90 just points at the Jetson's IP), or
  (b) Any existing broker (EMQX, cloud broker, etc.) both sides connect to.

--------------------------------------------------------------------------
ONE-TIME SETUP -- Option (a), broker on the Jetson itself:
    sudo apt update && sudo apt install -y mosquitto mosquitto-clients
    sudo systemctl enable --now mosquitto
    # Mosquitto now listens on 0.0.0.0:1883 by default.
    # (For anything beyond a lab bench, add authentication / TLS --
    #  the default config is open/unauthenticated.)

READER-SIDE CONFIG (FXR90 Admin Console):
    1. Communication > Zebra IoT Connector > Configuration
    2. Add Endpoint -> Endpoint Type: MQTT
    3. Connection: Server = <Jetson IP>, Port = 1883, Protocol = TCP
       (use 8883 + TLS if the broker is secured)
    4. Topics: set the "Tag Data Interface" topic, e.g. zebra/fxr90-01/tags
       (also set Management/Health topics if you want those too)
    5. Save, then set this endpoint as the active Data/Tag interface and
       start reading (Inventory mode, or via the Start REST/MQTT command).

Then on the Jetson:
    pip3 install paho-mqtt
    python3 jetson_mqtt_tag_subscriber.py --broker localhost --topic zebra/fxr90-01/tags
--------------------------------------------------------------------------

Reference: https://zebradevs.github.io/rfid-ziotc-docs/other_cloud_support/MQTT/web.html
"""

import argparse
import json
import logging

import paho.mqtt.client as mqtt

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
log = logging.getLogger("jetson-fxr90")


def handle_tag_event(payload: dict):
    """
    Called for every tag-data MQTT message. Adjust field names here if
    your reader's JSON schema differs -- check the raw payload once with
    --raw to confirm field names before wiring in downstream logic.
    """
    data = payload.get("data", payload)  # some configs nest under "data"
    epc_hex = data.get("idHex") or data.get("epc")
    antenna = data.get("antenna")
    rssi = data.get("peakRssi") or data.get("rssi")
    ts = data.get("firstSeenTimestamp") or data.get("timestamp")

    log.info("TAG epc=%s antenna=%s rssi=%s ts=%s", epc_hex, antenna, rssi, ts)

    # ---- Your downstream logic goes here ----
    # e.g. push to a database, trigger a CV pipeline correlation,
    # publish to another topic, write to a local queue, etc.


def on_connect(client, userdata, flags, reason_code, properties=None):
    if reason_code == 0:
        log.info("Connected to MQTT broker")
        client.subscribe(userdata["topic"])
        log.info("Subscribed to topic: %s", userdata["topic"])
    else:
        log.error("Connection failed, reason code=%s", reason_code)


def on_message(client, userdata, msg):
    raw = msg.payload.decode("utf-8", errors="replace")

    if userdata.get("raw"):
        log.info("RAW [%s] %s", msg.topic, raw)

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        log.warning("Non-JSON message on %s: %s", msg.topic, raw)
        return

    handle_tag_event(payload)


def on_disconnect(client, userdata, flags, reason_code, properties=None):
    log.warning("Disconnected from broker (reason=%s)", reason_code)


def main():
    parser = argparse.ArgumentParser(description="FXR90 MQTT tag subscriber for Jetson Orin")
    parser.add_argument("--broker", default="localhost", help="MQTT broker host")
    parser.add_argument("--port", type=int, default=1883, help="MQTT broker port")
    parser.add_argument("--topic", required=True,
                         help="Tag Data Interface topic configured on the FXR90, e.g. zebra/fxr90-01/tags")
    parser.add_argument("--username", default=None, help="MQTT username, if broker requires auth")
    parser.add_argument("--password", default=None, help="MQTT password, if broker requires auth")
    parser.add_argument("--raw", action="store_true", help="Also log the raw payload for debugging")
    args = parser.parse_args()

    client = mqtt.Client(
        mqtt.CallbackAPIVersion.VERSION2,
        userdata={"topic": args.topic, "raw": args.raw},
    )
    if args.username:
        client.username_pw_set(args.username, args.password)

    client.on_connect = on_connect
    client.on_message = on_message
    client.on_disconnect = on_disconnect

    log.info("Connecting to %s:%s ...", args.broker, args.port)
    client.connect(args.broker, args.port, keepalive=60)
    client.loop_forever()


if __name__ == "__main__":
    main()
