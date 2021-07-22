const setupVideo = (video: HTMLVideoElement, canvas: HTMLCanvasElement): Promise<void> => {
  return new Promise(async (resolve, reject) => {
    const width = 640
    const height = 480

    const constraints = {
      video: {
        width, height,
        frameRate: { ideal: 30, max: 30 }
      }
    }
    video.addEventListener('loadeddata', () => {
      canvas.width = video.videoWidth as number;
      canvas.height = video.videoHeight as number;
      resolve()
    });
    video.srcObject = await navigator.mediaDevices.getUserMedia(constraints)
  });
}

export default setupVideo;
