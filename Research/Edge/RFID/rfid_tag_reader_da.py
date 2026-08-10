#!/usr/bin/env python3
"""
rfid_tag_reader_da.py
----------------------
Data Analytics (DA) app that runs ON the Zebra FXR90's onboard Linux OS.
It uses the reader-resident `pyziotc` module (Zebra IoT Connector) to read
every RFID tag report coming from the Radio Control component and logs
EPC, RSSI, antenna port, and timestamp.

This is NOT run on the Jetson -- it runs on the reader itself. The reader
streams tag data to this script locally; you then choose whether to also
forward it off-reader (MQTT/HTTP/etc, already configured in the IoT
Connector Cloud settings) or consume it purely on-device.

--------------------------------------------------------------------------
DEV / TEST WORKFLOW (no packaging needed):
    scp rfid_tag_reader_da.py rfidadm@<reader-ip>:/apps/
    ssh rfidadm@<reader-ip>
    cd /apps
    python3 rfid_tag_reader_da.py

PRODUCTION DEPLOYMENT (persists across reboots, shows in Admin Console):
    Package as a .deb (see start_sample.sh / stop_sample.sh / DEBIAN/control
    pattern in Zebra's docs) and install via:
    Reader Admin Console -> Applications -> Install New Package -> Start
    (enable AutoStart if it should launch on reader boot)
--------------------------------------------------------------------------

Reference: https://github.com/ZebraDevs/RFID_ZIOTC_Examples (RFID-Monitor)
and https://zebradevs.github.io/rfid-ziotc-docs/
"""

import json
import time

import pyziotc


def new_msg_callback(msg_type, msg_in):
    """
    Called by the pyziotc runtime every time a new message arrives from
    the Radio Control. We only care about tag reports (MSG_IN_JSON) here.
    """
    if msg_type == pyziotc.MSG_IN_JSON:
        try:
            tag = json.loads(msg_in)
            data = tag.get("data", {})
            epc_hex = data.get("idHex")
            antenna = data.get("antenna")
            rssi = data.get("peakRssi")
            ts = data.get("firstSeenTimestamp") or data.get("timestamp")

            print(
                "TAG epc={} antenna={} rssi={} ts={}".format(
                    epc_hex, antenna, rssi, ts
                )
            )

            # Forward the raw tag JSON straight out the Data Interface so
            # it also reaches whatever cloud/MQTT/HTTP endpoint the IoT
            # Connector's Cloud tab is configured to send to. Comment this
            # out if you only want local console/logging behavior.
            ziotc.send_next_msg(pyziotc.MSG_OUT_DATA, bytearray(msg_in))

        except (ValueError, KeyError) as exc:
            print("Failed to parse tag message: {} ({})".format(msg_in, exc))

    elif msg_type == pyziotc.MSG_IN_GPI:
        print("GPI event: {}".format(msg_in))


ziotc = pyziotc.Ziotc()
ziotc.reg_new_msg_callback(new_msg_callback)

print("RFID tag reader DA app started -- waiting for tag reports...")

# In firmware >= 3.24.x the event loop is managed internally by pyziotc,
# so no explicit run_forever() is needed. We just keep the process alive.
while True:
    time.sleep(60)
