<?php

/**
 * Default device UUID + plaintext tokens (same rows as EdgeCompute/credentials.md).
 *
 * DemoSeeder and DeviceCredentialsSeeder use this so SCC does not depend on
 * EdgeCompute being checked out. Do not rotate these values.
 *
 * @return list<array{ref: string, uuid: string, token: string, type: string, notes: string}>
 */
return [
    ['ref' => 'DEV-RFID-01', 'uuid' => '51e63db5-21cf-4c50-ae9f-8b56569484d6', 'token' => 'ciaPx4otopY0vs0VQir1AL2TnWOMUjFm31MM9TebhD7qbcCi', 'type' => 'rfid', 'notes' => 'zebra/fxr90-01/tags · pole-01'],
    ['ref' => 'DEV-GAS-01', 'uuid' => 'ae7a8070-905f-4c1d-84fc-f92011dedb66', 'token' => 'j0pvrOgVScFcyEfH2Z54W3NEbm9fAwVv1A4QkliteVK83FLq', 'type' => 'gas', 'notes' => 'pole-01 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-01', 'uuid' => '7cd2d9bc-4986-4c07-9cd8-631a2eb66b02', 'token' => 'pBChoXvCFBbW3HR0aFaoRVFT5UpO5u5upnoztS7RSPMbw6g5', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-01'],
    ['ref' => 'DEV-CAM-PTZ-01', 'uuid' => '2cc1c159-a7fc-49b1-9832-f5a95336b769', 'token' => 'HnD38s2kK9HCIskO6bv09oRdIataTVMy8ofBufutxXlx5H4w', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-01'],
    ['ref' => 'DEV-RFID-02', 'uuid' => '303f3d6f-61b8-44a1-bc7a-7e01e9721d75', 'token' => 'Pox3wtVNvWyBIoMEnTSylJKjKMHQ1kn0HCEndkwFr6lnodHu', 'type' => 'rfid', 'notes' => 'zebra/fxr90-02/tags · pole-02'],
    ['ref' => 'DEV-GAS-02', 'uuid' => 'f9f5699c-273a-42ca-9895-d32353e6cf07', 'token' => 'HgOaM5b3KmTc7rIvxXgOHdDlKDNLBRInSWHXPDDhI70jrGhh', 'type' => 'gas', 'notes' => 'pole-02 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-02', 'uuid' => 'd3b4614a-1cf3-4ba1-a0f4-134b27f3a2b1', 'token' => 'PFiWgpOKiKwzIFX4e8VPI23GDt8cjhmeIG0w9o2bz6I3FjNg', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-02'],
    ['ref' => 'DEV-CAM-PTZ-02', 'uuid' => '902ed25d-fdc3-4468-99ed-df32850358f5', 'token' => 'VzqBRidczkOqRtoME0d5yKdPGLIzOv8o5IQ6XGN3Ynj21B7f', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-02'],
    ['ref' => 'DEV-RFID-03', 'uuid' => 'e9697b8e-96c2-4c60-a44e-f73ac81d15e5', 'token' => 'iE1nLGBomb96pptkFpGe0HmCTBbR2vYzVkjC8ztl3yTj1umm', 'type' => 'rfid', 'notes' => 'zebra/fxr90-03/tags · pole-03'],
    ['ref' => 'DEV-GAS-03', 'uuid' => '5b074ab3-48a8-444b-9b09-0a84fc590f38', 'token' => 'Q2z8Zh9nMzw8410nKXhVBqE5ZJ2Ux7Et2kAPsQSVoym4HMZu', 'type' => 'gas', 'notes' => 'pole-03 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-03', 'uuid' => 'f45f673d-bbdb-4c8c-bdf9-1151d1d66150', 'token' => 'OZh5lwx0DiJe8XlrofpFwPtM0KZuBmnwWJMrybaLQuJs1KOK', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-03'],
    ['ref' => 'DEV-CAM-PTZ-03', 'uuid' => 'c4d1526b-b475-45e2-b1db-362c21624c96', 'token' => 'nmlj3dtz8uLvdJ9rhqv2FtOxt53wvUJVSPMjHIrAMmOnNuU2', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-03'],
    ['ref' => 'DEV-RFID-04', 'uuid' => 'c9405253-8c73-4a1f-81b1-df69b9a238cc', 'token' => 'mIoJJD7aJEronne58LS0W9u44dOW65KpNawLt8JTeTRlsE0Q', 'type' => 'rfid', 'notes' => 'zebra/fxr90-04/tags · pole-04'],
    ['ref' => 'DEV-GAS-04', 'uuid' => '95dcac73-bdd4-4bb0-8966-b17a9c70a5b5', 'token' => 'g5yjVbyvNCQG1LoliusV28oEmWLa6iWbEKSv5CT0JsdD6LcU', 'type' => 'gas', 'notes' => 'pole-04 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-04', 'uuid' => '87661e14-d4b8-4651-b1c7-3ab3f034a988', 'token' => '2oxZMdcw85wWIQpWZPZixHBPUbD5AH3Qmhj1Is2U7OApzKCR', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-04'],
    ['ref' => 'DEV-CAM-PTZ-04', 'uuid' => '172695f5-1586-42ce-bc50-b3738265ebd7', 'token' => 'WAP12tdBkzpc3dla3rNkjnNDR1fqGhWTqD8lHKx1RJTNy49T', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-04'],
    ['ref' => 'DEV-RFID-GATE', 'uuid' => '7279351f-097a-41e7-9508-100cfc1ce4a7', 'token' => '0MNlBzs7Ey0Bt6kuEbDTCym9KPvRGv3UQCjiHBjbSlkIIauk', 'type' => 'rfid', 'notes' => 'Main Gate'],
    ['ref' => 'DEV-RFID-05', 'uuid' => '247d3b50-fadd-4cb2-a832-bac758373fa8', 'token' => '0pHtluGaqpISqNx6HlpShRpA2S3GCEn3XO39PquzBtrdYh9W', 'type' => 'rfid', 'notes' => 'zebra/fxr90-05/tags · pole-05'],
    ['ref' => 'DEV-GAS-05', 'uuid' => '4d3e5014-0c52-4de8-97c9-e830b94c9037', 'token' => 'H2yAMkrujWYQBr8idWrQXPbtJFrRdveh6r9jV8cgw0FiBXJd', 'type' => 'gas', 'notes' => 'pole-05 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-05', 'uuid' => '275dbb6f-6245-4de8-927b-7537febfbbe5', 'token' => 'dQBmiFmAW9EuhdzhleOta6eMr1hoKNToS1Ygv2tV9rhdnah1', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-05'],
    ['ref' => 'DEV-CAM-PTZ-05', 'uuid' => '8e3a1fdd-fe5a-4f6f-b0c2-eba5c83c92df', 'token' => 'sFhJXCUaBgbfIVpVONvWRl9i9sql7eMDztOb42wQEirzTST8', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-05'],
    ['ref' => 'DEV-RFID-06', 'uuid' => 'fe900c13-976c-47d6-809d-7b5726e75237', 'token' => 'W8rJozCPsDONqD6fWS8oI7xjA08hoLhd1O4Zc8b8Ig1EKc4p', 'type' => 'rfid', 'notes' => 'zebra/fxr90-06/tags · pole-06'],
    ['ref' => 'DEV-GAS-06', 'uuid' => '200faedd-d5aa-4017-aee5-c72750478e9c', 'token' => 'xyfIwpRR3KKt5stuFm6AuwJE8hwSij7Jp7iFHFLm8U7pIwGd', 'type' => 'gas', 'notes' => 'pole-06 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-06', 'uuid' => 'bf5fffe9-14ca-40d7-83d5-74b06d8eab0d', 'token' => 'hHoRYWhd1GYSI1ZpyyiNNwSeg0YrFyZCNlOV07cmDyMBFLDa', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-06'],
    ['ref' => 'DEV-CAM-PTZ-06', 'uuid' => 'ec412cf0-2d9a-46fc-96bf-2282adc30f24', 'token' => 'Fwg2lgXyPb0VOnef08syS02LoWTaotcdmJYwwKgt27cmCIxY', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-06'],
    ['ref' => 'DEV-RFID-07', 'uuid' => 'e47e6f95-2c15-4e58-a961-3a24e05c0cdd', 'token' => 'imZdhcoa4yOCbDBsxwtUC6rXmMnDCnAoad16hyJen4wwFQV4', 'type' => 'rfid', 'notes' => 'zebra/fxr90-07/tags · pole-07'],
    ['ref' => 'DEV-GAS-07', 'uuid' => 'd82eff93-26f3-4390-bcc7-03f7e5581baf', 'token' => 'RG30oAwpBZkGOyNRBFziUFzYGWQjI1GCegQhKimbZ06KXcyi', 'type' => 'gas', 'notes' => 'pole-07 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-07', 'uuid' => '9e314379-28ca-44e6-8c87-62d9b8146208', 'token' => 'hBddmfA9v8YrJDUXD13jCAy76wAsElXNSvfUYIKKNnlzQZeN', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-07'],
    ['ref' => 'DEV-CAM-PTZ-07', 'uuid' => '4c786700-bf96-4e55-96ed-3b982ae15c4b', 'token' => 'g5MlwpBRLhf3qSbPzhcnR3oowYGwFdC8f9pUVLpWRmQk1EWc', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-07'],
    ['ref' => 'DEV-RFID-08', 'uuid' => 'a855fa2f-181e-4909-ade5-63020d382fdd', 'token' => 'Hl46bVIPnRGLGpAhhcESQwt3ZSYkebQHysyk60Nk6u4H959y', 'type' => 'rfid', 'notes' => 'zebra/fxr90-08/tags · pole-08'],
    ['ref' => 'DEV-GAS-08', 'uuid' => '68bc986f-f5c2-4e71-9394-526a7a3fe331', 'token' => 'Q6GBiHekGbK4TkjuSiB5LARTpKi4tLhQ3WVWNQhjzWPiulzr', 'type' => 'gas', 'notes' => 'pole-08 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-08', 'uuid' => 'd4ad9579-d4e0-4607-94cd-5169add5596f', 'token' => 'tVYKLX5mUPv2a1iHYwknsqW4EdaZ5McLlsuuQ9gHvobJQdrG', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-08'],
    ['ref' => 'DEV-CAM-PTZ-08', 'uuid' => '0bfd03f4-df52-4067-8932-f13b3a399074', 'token' => 'emBxpzEauSAqCCiw8KtMaavgeG49VM8k5ocNQjSbTijVu4aF', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-08'],
];
