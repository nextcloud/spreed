import { PostProcessingConfig } from './postProcessingHelper'

export type RenderingPipeline = {
  render(): Promise<void>
  updatePostProcessingConfig(
    newPostProcessingConfig: PostProcessingConfig
  ): void
  // TODO Update background image only when loaded
  // updateBackgroundImage(backgroundImage: HTMLImageElement): void
  cleanUp(): void
}
