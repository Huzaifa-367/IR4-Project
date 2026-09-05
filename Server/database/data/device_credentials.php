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
    // SCC1 poles 5–8 (proper numbering — not remapped to 01–04)
    ['ref' => 'DEV-RFID-05', 'uuid' => 'a1068ec3-994f-45e4-bbc9-b7a9edb91eff', 'token' => 'IFfctO1pkXv6xKDkyX9cSRvTGVqRK6GALSv0h7MjZxYTsIrW', 'type' => 'rfid', 'notes' => 'zebra/fxr90-05/tags · pole-05'],
    ['ref' => 'DEV-GAS-05', 'uuid' => 'dd4cdf9e-ff1c-4f85-873b-cfce618e6563', 'token' => 'ZREA4a9xB0HHVdsF4cFkwpw9uDg4xism64W9ERJfvKnLzjW7', 'type' => 'gas', 'notes' => 'pole-05 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-05', 'uuid' => '0adfa302-a91b-4c26-9d83-9493eda876bb', 'token' => '2fymNnrGhhhrtGNcakUVY4K6cCWqbWtqjEVnkakT7M2yWTsB', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-05'],
    ['ref' => 'DEV-CAM-PTZ-05', 'uuid' => 'f1eb2073-7aea-404b-877b-dcafb0268ccb', 'token' => 'RfIt3hx4I3MfudnUrt4J73NyO9swR0uO3jCDgtl3dLC1EC8R', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-05'],
    ['ref' => 'DEV-RFID-06', 'uuid' => 'e4b4dbd2-5086-4b5f-aed6-3abd40b03ec9', 'token' => 'bXbWIsu5YdkbnE3ziloYoqnoROQLq0t2xFPmpuLBYiUoZ3xL', 'type' => 'rfid', 'notes' => 'zebra/fxr90-06/tags · pole-06'],
    ['ref' => 'DEV-GAS-06', 'uuid' => '169b490b-204f-4b3d-ba07-0ab61243e519', 'token' => '0d0eaWh3Jhy5qiYFgukyKtgp8j4cugtyLmAJQjbcstdVgMu6', 'type' => 'gas', 'notes' => 'pole-06 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-06', 'uuid' => '15bd8f08-3c73-402d-9bbc-8f37af430ab6', 'token' => 'mklm0mAIzo5NvnUL2pX77hJJb3pMCM9zWYP1tx0Xdt3i74aU', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-06'],
    ['ref' => 'DEV-CAM-PTZ-06', 'uuid' => '9506e821-441e-4f50-b679-b17653276a0e', 'token' => 'QAaBx8TMDbFb1nTFZjXjtjqLXhBeyqx7NnSm1MyhcW6XHTm7', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-06'],
    ['ref' => 'DEV-RFID-07', 'uuid' => 'e6b52803-3244-45ea-b39d-06451f0ff182', 'token' => 'eVGbeO1sZiHHgyUQVRjg1JSpJMUtzazo4wYmUCiORrFMLKpc', 'type' => 'rfid', 'notes' => 'zebra/fxr90-07/tags · pole-07'],
    ['ref' => 'DEV-GAS-07', 'uuid' => 'b40788c3-161c-40de-a3f9-90e122969c7a', 'token' => '5JRdxlQtjorV5yhp5hhMbBzR3FZPEQG3g7XFftcqh0eniMJh', 'type' => 'gas', 'notes' => 'pole-07 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-07', 'uuid' => '140976aa-bc51-46d6-942e-65b564276c2b', 'token' => '0n3cuS96gwD66MQv4hKigmrNOElSUUY6N3SG0WdNXLrnEyIs', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-07'],
    ['ref' => 'DEV-CAM-PTZ-07', 'uuid' => '7093a112-cbce-4208-b97e-434d3b87acd9', 'token' => '8aC0UX05P4f5SB4ZXmUgq27ckbIVsevjTYzMyT8SY854Dpvs', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-07'],
    ['ref' => 'DEV-RFID-08', 'uuid' => '4473d308-54fb-41bc-89bc-1ade01c426f9', 'token' => 'qHtNK442lwbE2yk8mbDqpxRmm5OL81kRReFXXWqPYNJlKzbq', 'type' => 'rfid', 'notes' => 'zebra/fxr90-08/tags · pole-08'],
    ['ref' => 'DEV-GAS-08', 'uuid' => '4c8f42bd-167c-4146-b42d-04c276fe5f33', 'token' => '70oGdslSVdiTLUOofto4jPXc5lWTqzTJ5KkqcRf0kBaHrzVE', 'type' => 'gas', 'notes' => 'pole-08 · YT-98H slaves 1–5'],
    ['ref' => 'DEV-CAM-FIXED-08', 'uuid' => 'a3c17d64-9ab6-40a0-9e32-dc00c4fbec91', 'token' => 'AtqhHlcSDfzfxvtH6MclnPOg1DCybAJdHPrNdRn9tRZylrX2', 'type' => 'cam_ai', 'notes' => 'PPE AI · camera_ref CAM-FIXED-08'],
    ['ref' => 'DEV-CAM-PTZ-08', 'uuid' => 'ca68720a-b971-497c-be6e-cda50f31b02b', 'token' => '8USbz6CO1FPKKDLCMwe4PvcoRzAZqun5MP7TmOfbGZoJNxJF', 'type' => 'cam_ai', 'notes' => 'Overview AI · camera_ref CAM-PTZ-08'],
];
