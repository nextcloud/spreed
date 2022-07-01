# Call experience

There are multiple factors which can have a smaller and bigger impact on the experience of a call.

## Automatic selection of sent video quality

Since version 8.0.8 Nextcloud Talk will automatically select the sent video quality depending on the number of participants sending video and/or audio streams. Both counters are checked and then the lowest matching quality is selected.

If a participant enabled their microphone and starts speaking the video quality is set to "High" for the duration of them talking plus an additional grace period of 5 seconds.

### Number of video streams

| Streams | Quality   |
|---------|-----------|
| < 4     | High      |
| 4-7     | Medium    |
| 8-10    | Low       |
| 11-14   | Very low  |
| > 14    | Thumbnail |

### Number of audio streams

| Streams | Quality   |
|---------|-----------|
| < 10    | High      |
| 10-19   | Medium    |
| 20-29   | Low       |
| 30-39   | Very low  |
| > 40    | Thumbnail |

### Video qualities

| Quality   | Max width | Ideal width | Max height | Ideal height | Max frames | Ideal frames |
|-----------|-----------|-------------|------------|--------------|------------|--------------|
| High      | -         | 720         | -          | 540          | -          | 30           |
| Medium    | 640       | 560         | 480        | 420          | 24         | 24           |
| Low       | 480       | 360         | 320        | 270          | 15         | 15           |
| Very low  | 320       | -           | 240        | -            | 8          | -            |
| Thumbnail | 320       | -           | 240        | -            | 1          | -            |

## Judging the connection quality

Similar since version 9.0.2 Nextcloud Talk is having an eye on the lost packages and the "round trip time" of the stream data. When the connection is too bad or no data could be transmitted at all, the participant will be informed to try to disable their own video and screen share.
If those two are already off, the participant will see a message that their connection bandwidth or device cannot withhold the necessary load for participating in a call.

The critical values are:

**Lost packages:** 30% in the last 5 seconds

**Too few packages:** Less than 10 packages per second

**Round trip time:** above 1.5 seconds
