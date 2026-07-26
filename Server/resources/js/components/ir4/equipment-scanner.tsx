import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { parseEquipmentQrToken } from '@/lib/equipment-qr';

type BarcodeDetectorLike = {
    detect: (source: ImageBitmapSource) => Promise<Array<{ rawValue: string }>>;
};

type BarcodeDetectorConstructor = new (options?: {
    formats?: string[];
}) => BarcodeDetectorLike;

function getBarcodeDetector(): BarcodeDetectorConstructor | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const ctor = (
        window as Window & { BarcodeDetector?: BarcodeDetectorConstructor }
    ).BarcodeDetector;

    return ctor ?? null;
}

type Props = {
    onToken: (qrToken: string) => void;
    busy?: boolean;
};

export function EquipmentScanner({ onToken, busy = false }: Props) {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const rafRef = useRef<number | null>(null);
    const [cameraError, setCameraError] = useState<string | null>(null);
    const [cameraActive, setCameraActive] = useState(false);
    const [manualToken, setManualToken] = useState('');
    const [manualError, setManualError] = useState<string | null>(null);
    const detectorSupported = getBarcodeDetector() !== null;

    useEffect(() => {
        return () => {
            stopCamera();
        };
    }, []);

    function stopCamera(): void {
        if (rafRef.current !== null) {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = null;
        }

        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
        setCameraActive(false);
    }

    async function startCamera(): Promise<void> {
        setCameraError(null);
        const Detector = getBarcodeDetector();

        if (!Detector) {
            setCameraError(
                'BarcodeDetector is not supported in this browser. Enter the token manually.',
            );

            return;
        }

        if (!navigator.mediaDevices?.getUserMedia) {
            setCameraError(
                'Camera access is not available. Enter the token manually.',
            );

            return;
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            streamRef.current = stream;
            const video = videoRef.current;

            if (!video) {
                stopCamera();

                return;
            }

            video.srcObject = stream;
            await video.play();
            setCameraActive(true);

            const detector = new Detector({ formats: ['qr_code'] });
            let scanning = true;

            const tick = async (): Promise<void> => {
                if (!scanning || !videoRef.current) {
                    return;
                }

                try {
                    const codes = await detector.detect(videoRef.current);

                    if (codes.length > 0) {
                        const token = parseEquipmentQrToken(codes[0].rawValue);

                        if (token) {
                            scanning = false;
                            stopCamera();
                            onToken(token);

                            return;
                        }
                    }
                } catch {
                    // Frame decode can fail transiently — keep scanning.
                }

                rafRef.current = requestAnimationFrame(() => {
                    void tick();
                });
            };

            void tick();
        } catch {
            setCameraError(
                'Could not open the camera. Check permissions or enter the token manually.',
            );
            stopCamera();
        }
    }

    function submitManual(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        setManualError(null);
        const token = parseEquipmentQrToken(manualToken);

        if (!token) {
            setManualError(
                'Enter a valid QR token (UUID) or a public /e/{token} URL.',
            );

            return;
        }

        onToken(token);
    }

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <p className="text-sm text-muted-foreground">
                    Scan the equipment QR with the device camera, or paste the
                    token if the camera is unavailable.
                </p>
                {detectorSupported ? (
                    <div className="flex flex-wrap gap-2">
                        {!cameraActive ? (
                            <Button
                                type="button"
                                onClick={() => void startCamera()}
                                disabled={busy}
                            >
                                Start camera scan
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={stopCamera}
                            >
                                Stop camera
                            </Button>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-amber-800 dark:text-amber-200">
                        This browser does not support BarcodeDetector — use
                        manual entry.
                    </p>
                )}
                {cameraError && (
                    <p className="text-sm text-destructive">{cameraError}</p>
                )}
                <video
                    ref={videoRef}
                    className={
                        cameraActive
                            ? 'aspect-video w-full max-w-md rounded-md border border-border bg-black object-cover'
                            : 'hidden'
                    }
                    muted
                    playsInline
                />
            </div>

            <form
                onSubmit={submitManual}
                className="space-y-2 border-t border-border pt-4"
            >
                <Label htmlFor="manual_qr_token">Manual token / URL</Label>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Input
                        id="manual_qr_token"
                        value={manualToken}
                        onChange={(event) => setManualToken(event.target.value)}
                        placeholder="UUID or https://…/e/{token}"
                        disabled={busy}
                    />
                    <Button type="submit" disabled={busy}>
                        Look up
                    </Button>
                </div>
                {manualError && (
                    <p className="text-sm text-destructive">{manualError}</p>
                )}
            </form>
        </div>
    );
}
