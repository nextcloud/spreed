export interface IRnnoiseModule extends EmscriptenModule {
    _rnnoise_create: () => number;
    _rnnoise_destroy: (context: number) => void;
    _rnnoise_process_frame: (context: number, input: number, output: number) => number;
}
/**
 * Constant. Rnnoise default sample size, samples of different size won't work.
 */
export declare const RNNOISE_SAMPLE_LENGTH = 480;
/**
 * Represents an adaptor for the rnnoise library compiled to webassembly. The class takes care of webassembly
 * memory management and exposes rnnoise functionality such as PCM audio denoising and VAD (voice activity
 * detection) scores.
 */
export default class RnnoiseProcessor {
    /**
     * Rnnoise context object needed to perform the audio processing.
     */
    private _context;
    /**
     * State flag, check if the instance was destroyed.
     */
    private _destroyed;
    /**
     * WASM interface through which calls to rnnoise are made.
     */
    private _wasmInterface;
    /**
     * WASM dynamic memory buffer used as input for rnnoise processing method.
     */
    private _wasmPcmInput;
    /**
     * The Float32Array index representing the start point in the wasm heap of the _wasmPcmInput buffer.
     */
    private _wasmPcmInputF32Index;
    /**
     * Constructor.
     *
     * @class
     * @param {Object} wasmInterface - WebAssembly module interface that exposes rnnoise functionality.
     */
    constructor(wasmInterface: IRnnoiseModule);
    /**
     * Release resources associated with the wasm context. If something goes downhill here
     * i.e. Exception is thrown, there is nothing much we can do.
     *
     * @returns {void}
     */
    _releaseWasmResources(): void;
    /**
     * Rnnoise can only operate on a certain PCM array size.
     *
     * @returns {number} - The PCM sample array size as required by rnnoise.
     */
    getSampleLength(): number;
    /**
     * Rnnoise can only operate on a certain format of PCM sample namely float 32 44.1Kz.
     *
     * @returns {number} - PCM sample frequency as required by rnnoise.
     */
    getRequiredPCMFrequency(): number;
    /**
     * Release any resources required by the rnnoise context this needs to be called
     * before destroying any context that uses the processor.
     *
     * @returns {void}
     */
    destroy(): void;
    /**
     * Calculate the Voice Activity Detection for a raw Float32 PCM sample Array.
     * The size of the array must be of exactly 480 samples, this constraint comes from the rnnoise library.
     *
     * @param {Float32Array} pcmFrame - Array containing 32 bit PCM samples.
     * @returns {Float} Contains VAD score in the interval 0 - 1 i.e. 0.90.
     */
    calculateAudioFrameVAD(pcmFrame: Float32Array): number;
    /**
     * Process an audio frame, optionally denoising the input pcmFrame and returning the Voice Activity Detection score
     * for a raw Float32 PCM sample Array.
     * The size of the array must be of exactly 480 samples, this constraint comes from the rnnoise library.
     *
     * @param {Float32Array} pcmFrame - Array containing 32 bit PCM samples. Parameter is also used as output
     * when {@code shouldDenoise} is true.
     * @param {boolean} shouldDenoise - Should the denoised frame be returned in pcmFrame.
     * @returns {Float} Contains VAD score in the interval 0 - 1 i.e. 0.90 .
     */
    processAudioFrame(pcmFrame: Float32Array, shouldDenoise?: boolean): number;
}
