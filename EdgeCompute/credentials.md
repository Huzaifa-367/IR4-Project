# Device credentials

Store in EdgeCompute `secrets.env` — shown once at `ir4:install` / DemoSeeder.


| Type   | Reference        | UUID                                 | Token                                            | Notes                               |
| ------ | ---------------- | ------------------------------------ | ------------------------------------------------ | ----------------------------------- |
| rfid   | DEV-RFID-01      | 51e63db5-21cf-4c50-ae9f-8b56569484d6 | ciaPx4otopY0vs0VQir1AL2TnWOMUjFm31MM9TebhD7qbcCi | zebra/fxr90-01/tags · pole-01       |
| gas    | DEV-GAS-01       | ae7a8070-905f-4c1d-84fc-f92011dedb66 | j0pvrOgVScFcyEfH2Z54W3NEbm9fAwVv1A4QkliteVK83FLq | pole-01 · YT-98H slaves 1–5         |
| cam_ai | DEV-CAM-FIXED-01 | 7cd2d9bc-4986-4c07-9cd8-631a2eb66b02 | pBChoXvCFBbW3HR0aFaoRVFT5UpO5u5upnoztS7RSPMbw6g5 | PPE AI · camera_ref CAM-FIXED-01    |
| cam_ai | DEV-CAM-PTZ-01   | 2cc1c159-a7fc-49b1-9832-f5a95336b769 | HnD38s2kK9HCIskO6bv09oRdIataTVMy8ofBufutxXlx5H4w | Overview AI · camera_ref CAM-PTZ-01 |
| rfid   | DEV-RFID-02      | 303f3d6f-61b8-44a1-bc7a-7e01e9721d75 | Pox3wtVNvWyBIoMEnTSylJKjKMHQ1kn0HCEndkwFr6lnodHu | zebra/fxr90-02/tags · pole-02       |
| gas    | DEV-GAS-02       | f9f5699c-273a-42ca-9895-d32353e6cf07 | HgOaM5b3KmTc7rIvxXgOHdDlKDNLBRInSWHXPDDhI70jrGhh | pole-02 · YT-98H slaves 1–5         |
| cam_ai | DEV-CAM-FIXED-02 | d3b4614a-1cf3-4ba1-a0f4-134b27f3a2b1 | PFiWgpOKiKwzIFX4e8VPI23GDt8cjhmeIG0w9o2bz6I3FjNg | PPE AI · camera_ref CAM-FIXED-02    |
| cam_ai | DEV-CAM-PTZ-02   | 902ed25d-fdc3-4468-99ed-df32850358f5 | VzqBRidczkOqRtoME0d5yKdPGLIzOv8o5IQ6XGN3Ynj21B7f | Overview AI · camera_ref CAM-PTZ-02 |
| rfid   | DEV-RFID-03      | e9697b8e-96c2-4c60-a44e-f73ac81d15e5 | iE1nLGBomb96pptkFpGe0HmCTBbR2vYzVkjC8ztl3yTj1umm | zebra/fxr90-03/tags · pole-03       |
| gas    | DEV-GAS-03       | 5b074ab3-48a8-444b-9b09-0a84fc590f38 | Q2z8Zh9nMzw8410nKXhVBqE5ZJ2Ux7Et2kAPsQSVoym4HMZu | pole-03 · YT-98H slaves 1–5         |
| cam_ai | DEV-CAM-FIXED-03 | f45f673d-bbdb-4c8c-bdf9-1151d1d66150 | OZh5lwx0DiJe8XlrofpFwPtM0KZuBmnwWJMrybaLQuJs1KOK | PPE AI · camera_ref CAM-FIXED-03    |
| cam_ai | DEV-CAM-PTZ-03   | c4d1526b-b475-45e2-b1db-362c21624c96 | nmlj3dtz8uLvdJ9rhqv2FtOxt53wvUJVSPMjHIrAMmOnNuU2 | Overview AI · camera_ref CAM-PTZ-03 |
| rfid   | DEV-RFID-04      | c9405253-8c73-4a1f-81b1-df69b9a238cc | mIoJJD7aJEronne58LS0W9u44dOW65KpNawLt8JTeTRlsE0Q | zebra/fxr90-04/tags · pole-04       |
| gas    | DEV-GAS-04       | 95dcac73-bdd4-4bb0-8966-b17a9c70a5b5 | g5yjVbyvNCQG1LoliusV28oEmWLa6iWbEKSv5CT0JsdD6LcU | pole-04 · YT-98H slaves 1–5         |
| cam_ai | DEV-CAM-FIXED-04 | 87661e14-d4b8-4651-b1c7-3ab3f034a988 | 2oxZMdcw85wWIQpWZPZixHBPUbD5AH3Qmhj1Is2U7OApzKCR | PPE AI · camera_ref CAM-FIXED-04    |
| cam_ai | DEV-CAM-PTZ-04   | 172695f5-1586-42ce-bc50-b3738265ebd7 | WAP12tdBkzpc3dla3rNkjnNDR1fqGhWTqD8lHKx1RJTNy49T | Overview AI · camera_ref CAM-PTZ-04 |
| rfid   | DEV-RFID-GATE    | 7279351f-097a-41e7-9508-100cfc1ce4a7 | 0MNlBzs7Ey0Bt6kuEbDTCym9KPvRGv3UQCjiHBjbSlkIIauk | Main Gate                           |


Each pole: DEV-RFID / DEV-GAS / DEV-CAM-FIXED / DEV-CAM-PTZ + CAM-FIXED / CAM-PTZ streams.

Server seeds the same rows from `Server/database/data/device_credentials.php`.
Poles: `ir4-edge secrets --pole N` copies this table into `configs/secrets.env`.
