/*
 * The barcode scanner page (resources/views/scanner/index.blade.php).
 * Loaded only there, after app.js, so window.gfSwal is ready.
 *
 * The camera is read and decoded in the browser with ZXing: browsers'
 * native BarcodeDetector isn't available in desktop Chrome on Windows.
 * Opening the modal starts the camera, closing it (Cancel, Esc, backdrop)
 * stops it, and the first label read goes to scanner.lookup, which opens
 * the record.
 */
import { BrowserMultiFormatReader } from '@zxing/browser';
import { BarcodeFormat, DecodeHintType } from '@zxing/library';

const dialog = document.getElementById('scanner-modal');
const opener = document.querySelector('[data-scanner-open]');

if (dialog && opener) {
    const video = dialog.querySelector('[data-scanner-video]');
    const status = dialog.querySelector('[data-scanner-status]');
    const cameraField = dialog.querySelector('[data-scanner-camera-field]');
    const cameraSelect = dialog.querySelector('[data-scanner-camera]');

    // Inventory labels are Code 128 only; one format (and no others to try)
    // keeps each frame quick, and TRY_HARDER helps with a slightly blurry label.
    const hints = new Map([
        [DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.CODE_128]],
        [DecodeHintType.TRY_HARDER, true],
    ]);
    const reader = new BrowserMultiFormatReader(hints, { delayBetweenScanAttempts: 100 });

    let controls = null;
    // Bumped on every start and stop, so a camera that finishes starting
    // after the modal was closed (or another camera was picked) is let go.
    let session = 0;

    const stop = () => {
        session++;
        controls?.stop();
        controls = null;
    };

    const showError = (title, text) => {
        stop();
        dialog.close();
        window.gfSwal.alert({ icon: 'error', title, text });
    };

    // A thin 1D barcode needs pixels: ask for 720p, from the rear camera on a
    // phone or tablet (a desktop webcam ignores facingMode), or the one picked.
    const constraints = (deviceId) => ({
        audio: false,
        video: {
            ...(deviceId ? { deviceId: { exact: deviceId } } : { facingMode: { ideal: 'environment' } }),
            width: { ideal: 1280 },
            height: { ideal: 720 },
        },
    });

    // The picker only appears with more than one camera. Device names are
    // only readable once the camera has been allowed, so it's filled after.
    const listCameras = async () => {
        const cameras = await BrowserMultiFormatReader.listVideoInputDevices();

        cameraField.hidden = cameras.length < 2;

        if (cameras.length < 2) {
            return;
        }

        const current = controls?.streamVideoSettingsGet?.(() => true)?.deviceId;

        cameraSelect.replaceChildren(...cameras.map((camera, index) => new Option(
            camera.label || `Camera ${index + 1}`,
            camera.deviceId,
            false,
            camera.deviceId === current,
        )));
    };

    const found = (code) => {
        stop();
        dialog.close();
        window.gfSwal.processing('Looking up barcode…', code);

        const url = new URL(dialog.dataset.lookupUrl, window.location.href);
        url.searchParams.set('code', code);
        window.location.assign(url);
    };

    const start = async (deviceId = null) => {
        stop();
        const mine = session;

        status.textContent = 'Starting the camera…';

        try {
            const started = await reader.decodeFromConstraints(constraints(deviceId), video, (result) => {
                // Frames without a readable barcode come back as errors
                // (NotFoundException and friends); just wait for the next one.
                if (result && mine === session) {
                    found(result.getText().trim());
                }
            });

            if (mine !== session) {
                started.stop();

                return;
            }

            controls = started;
            status.textContent = 'Looking for a barcode…';
            // Without the list the default camera still scans; no picker, that's all.
            listCameras().catch(() => {});
        } catch (error) {
            if (mine !== session) {
                return;
            }

            if (error?.name === 'NotAllowedError' || error?.name === 'SecurityError') {
                showError('Camera blocked', 'Allow this site to use the camera (the camera icon in the address bar), then try again — or type the barcode in below.');
            } else if (error?.name === 'NotFoundError' || error?.name === 'OverconstrainedError') {
                showError('No camera found', 'This device has no camera the browser can use. Type the barcode in below instead.');
            } else if (error?.name === 'NotReadableError') {
                showError('Camera in use', 'Another app (Teams, Zoom…) may be using the camera. Close it and try again, or type the barcode in below.');
            } else {
                showError('Camera unavailable', 'The camera could not be started. Type the barcode in below instead.');
            }
        }
    };

    // Runs before app.js's document-level handler opens the modal, so it can
    // keep it shut when the browser won't give pages a camera at all.
    opener.addEventListener('click', (event) => {
        if (! window.isSecureContext || ! navigator.mediaDevices?.getUserMedia) {
            event.preventDefault();
            event.stopPropagation();
            window.gfSwal.alert({
                icon: 'error',
                title: 'Camera needs a secure connection',
                text: 'Browsers only let a page use the camera over HTTPS (or on localhost). Type the barcode in below instead.',
            });

            return;
        }

        start();
    });

    cameraSelect.addEventListener('change', () => start(cameraSelect.value));

    // Every way the modal closes — Cancel, the X, Esc, the backdrop — ends here.
    dialog.addEventListener('close', stop);

    // Leaving the page (or it going into the Back-button cache) frees the camera.
    window.addEventListener('pagehide', stop);
}
