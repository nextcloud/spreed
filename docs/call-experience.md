# Call experience

There are multiple factors which can have a smaller and bigger impact on the experience of a call.

## Automatic selection of sent video quality

Since version 8.0.8 Nextcloud Talk will automatically select the sent video quality depending on the number of participants sending video and/or audio streams. Both counters are checked and then the lowest matching quality is selected.

If a participant enabled their microphone and starts speaking the video quality is set to "High" for the duration of them talking plus an additional grace period of 5 seconds.

### Number of video streams

| Streams | Quality        |
|---------|----------------|
| < 20    | High           |
| 21-80   | Medium         |
| 80-119  | Low / Very low |
| > 120   | Thumbnail      |

### Number of audio streams

| Streams | Quality   |
|---------|-----------|
| < 40    | High      |
| 40-79   | Medium    |
| 80-119  | Low       |
| 120-199 | Very low  |
| > 200   | Thumbnail |

### Video qualities

Frames:
- Max frames: 30
- Ideal frames: 30
- Min frames: 20

| Quality   | Max width | Ideal width | Min width | Max height | Ideal height | Min height |
|-----------|-----------|-------------|-----------|------------|--------------|------------|
| High      | -         | -           | 1440      | -          | -            | 1080       |
| Medium    | -         | -           | 720       | -          | -            | 540        |
| Low       | 640       | 560         | 480       | 480        | 420          | 320        |
| Very low  | 480       | 360         | 320       | 320        | 270          | 240        |
| Thumbnail | 320       | -           | -         | 240        | -            | -          |

*These values were last changes in Nextcloud Talk 24.0.1, 23.0.7 and 22.0.14*

## Judging the connection quality

Similar since version 9.0.2 Nextcloud Talk is having an eye on the lost packages and the "round trip time" of the stream data. When the connection is too bad or no data could be transmitted at all, the participant will be informed to try to disable their own video and screen share.
If those two are already off, the participant will see a message that their connection bandwidth or device cannot withhold the necessary load for participating in a call.

The critical values are:

**Lost packages:** 30% in the last 5 seconds

**Too few packages:** Less than 10 packages per second

**Round trip time:** above 1.5 seconds
