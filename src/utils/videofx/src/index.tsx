import { blur } from './core/vanilla/blur';
import setupVideo from './core/vanilla/track';

(async () => {
  const video = document.getElementById('input') as HTMLVideoElement;
  const canvas = document.getElementById('output') as HTMLCanvasElement;
  await setupVideo(video, canvas);
  await blur(video, canvas);
})();
