# Encoder configuration

The encoders used by the recording server can be customized in its configuration file.

By default [VP8](https://en.wikipedia.org/wiki/VP8) is used as the video codec. VP8 is an open and royalty-free video compression format widely supported. Please check https://trac.ffmpeg.org/wiki/Encode/VP8, https://www.webmproject.org/docs/encoder-parameters and https://ffmpeg.org/ffmpeg-codecs.html#libvpx for details on the configuration options.

Similarly, [Opus](https://en.wikipedia.org/wiki/Opus), another open codec, is used for audio. Please check https://ffmpeg.org/ffmpeg-codecs.html#libopus-1 for details on the configuration options.

Nevertheless, please note that VP8 and Opus are just the default ones and that the encoders can be changed to any other supported by FFmpeg if needed. In that case the default container format, [WebM](https://en.wikipedia.org/wiki/WebM), may need to be changed as well, as it is specifically designed for VP8/VP9/AV1 and Vorbis/Opus.

## Benchmark tool

A benchmark tool is provided to check the resources used by the recorder process as well as the quality of the output file using different configurations.

The benchmark tool does not record an actual call; it plays a video file and records its audio and video (or, optionally, only its audio). This makes possible to easily compare the quality between different configurations, as they can be generated from the same input. There is no default input file, though; a specific file must be provided.

### Usage example

The different options accepted by the benchmark tool can be seen with `nextcloud-talk-recording-benchmark --help` (or, if the helper script is not available, directly with `python3 -m nextcloud.talk.recording.Benchmark --help`).

Each run of the benchmark tool records a single video (or audio) file with the given options. Using a Bash script several runs can be batched to check the result of running different options. For example:
```
#!/usr/bin/bash

# Define the output video options for ffmpeg and the filename suffix to use for each test.
TESTS=(
    "-c:v libvpx -deadline:v realtime -b:v 0,rt-b0"
    "-c:v libvpx -deadline:v realtime -b:v 0 -cpu-used:v 0,rt-b0-cpu0"
    "-c:v libvpx -deadline:v realtime -b:v 0 -cpu-used:v 15,rt-b0-cpu15"
    "-c:v libvpx -deadline:v realtime -b:v 0 -crf 4,rt-b0-crf4"
    "-c:v libvpx -deadline:v realtime -b:v 0 -crf 10,rt-b0-crf10"
    "-c:v libvpx -deadline:v realtime -b:v 0 -crf 32,rt-b0-crf32"
    "-c:v libvpx -deadline:v realtime -b:v 0 -crf 32 -cpu-used:v 0,rt-b0-crf32-cpu0"
    "-c:v libvpx -deadline:v realtime -b:v 0 -crf 32 -cpu-used:v 15,rt-b0-crf32-cpu15"
    "-c:v libvpx -deadline:v realtime -b:v 500k,rt-b500k"
    "-c:v libvpx -deadline:v realtime -b:v 500k -crf 4,rt-b500k-crf4"
    "-c:v libvpx -deadline:v realtime -b:v 500k -crf 10,rt-b500k-crf10"
    "-c:v libvpx -deadline:v realtime -b:v 500k -crf 32,rt-b500k-crf32"
    "-c:v libvpx -deadline:v realtime -b:v 750k,rt-b750k"
    "-c:v libvpx -deadline:v realtime -b:v 750k -crf 4,rt-b750k-crf4"
    "-c:v libvpx -deadline:v realtime -b:v 750k -crf 10,rt-b750k-crf10"
    "-c:v libvpx -deadline:v realtime -b:v 750k -crf 32,rt-b750k-crf32"
    "-c:v libvpx -deadline:v realtime -b:v 1000k,rt-b1000k"
    "-c:v libvpx -deadline:v realtime -b:v 1000k -crf 4,rt-b1000k-crf4"
    "-c:v libvpx -deadline:v realtime -b:v 1000k -crf 10,rt-b1000k-crf10"
    "-c:v libvpx -deadline:v realtime -b:v 1000k -crf 32,rt-b1000k-crf32"
)

for TEST in "${TESTS[@]}"
do
    # Split the input tuple on ","
    IFS="," read FFMPEG_OUTPUT_VIDEO FILENAME_SUFFIX <<< "${TEST}"
    # Run the test
    nextcloud-talk-recording-benchmark --length 300 --ffmpeg-output-video "${FFMPEG_OUTPUT_VIDEO}" /tmp/recording/files/example.mkv /tmp/recording/files/test-"${FILENAME_SUFFIX}".webm
done
```
