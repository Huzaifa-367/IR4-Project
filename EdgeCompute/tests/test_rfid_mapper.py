"""RFID mapper — FXR90 MQTT shapes to ingest events."""

from __future__ import annotations

import unittest

from ir4_edge.rfid.mapper import events_from_payload, extract_tag_fields


class RfidMapperTest(unittest.TestCase):
    def test_custom_envelope(self) -> None:
        payload = {
            "data": {
                "idHex": "aa0004ef55555555aa21bf43",
                "peakRssi": -34,
                "antenna": 4,
            },
            "timestamp": "2026-08-13T09:14:28.651+0000",
            "type": "CUSTOM",
        }
        fields = extract_tag_fields(payload)
        assert fields is not None
        self.assertEqual(fields["tag_uid"], "AA0004EF55555555AA21BF43")
        events = events_from_payload(payload, "DEV-RFID-01")
        self.assertEqual(len(events), 1)
        self.assertEqual(events[0]["reader_ref"], "DEV-RFID-01")

    def test_flat_tag_without_custom_type(self) -> None:
        payload = {
            "data": {
                "idHex": "07D7FEF80080507FEFF5CB6DB0649CB6",
                "peakRssi": -53,
                "antenna": 4,
            },
            "timestamp": "2026-08-21T21:04:37.086+03:00",
        }
        fields = extract_tag_fields(payload)
        assert fields is not None
        self.assertEqual(fields["tag_uid"], "07D7FEF80080507FEFF5CB6DB0649CB6")

    def test_health_envelope_skipped(self) -> None:
        payload = {
            "system": {"temperature": {"ambient": 32.0}},
            "radio_control": {"numTagReads": 100},
            "timestamp": "2026-08-22T12:00:00+03:00",
        }
        self.assertIsNone(extract_tag_fields(payload))
        self.assertEqual(events_from_payload(payload, "DEV-RFID-04"), [])


if __name__ == "__main__":
    unittest.main()
