<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023 Kate Döen <kate.doeen@nextcloud.com>
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk;

/**
 * @psalm-type TalkBot = array{
 *     description: ?string,
 *     id: int,
 *     name: string,
 *     state: int,
 * }
 *
 * @psalm-type TalkBotWithDetails = TalkBot&array{
 *     error_count: int,
 *     features: int,
 *     last_error_date: int,
 *     last_error_message: string,
 *     url: string,
 *     url_hash: string,
 * }
 *
 * @psalm-type TalkBotWithDetailsAndSecret = TalkBotWithDetails&array{
 *     secret: string,
 * }
 *
 * @psalm-type TalkCallPeer = array{
 *     actorId: string,
 *     actorType: string,
 *     displayName: string,
 *     lastPing: int,
 *     sessionId: string,
 *     token: string,
 * }
 *
 * @psalm-type TalkChatMentionSuggestion = array{
 *     id: string,
 *     label: string,
 *     source: string,
 *     status: ?string,
 *     statusClearAt: ?int,
 *     statusIcon: ?string,
 *     statusMessage: ?string,
 * }
 *
 * @psalm-type TalkChatMessage = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     deleted?: true,
 *     expirationTimestamp: int,
 *     id: int,
 *     isReplyable: bool,
 *     markdown: bool,
 *     message: string,
 *     messageParameters: array<string, array<string, mixed>>,
 *     messageType: string,
 *     reactions: array<string, integer>|\stdClass,
 *     referenceId: string,
 *     systemMessage: string,
 *     timestamp: int,
 *     token: string,
 *     lastEditActorDisplayName?: string,
 *     lastEditActorId?: string,
 *     lastEditActorType?: string,
 *     lastEditTimestamp?: int,
 *     silent?: bool,
 * }
 *
 * @psalm-type TalkChatMessageWithParent = TalkChatMessage&array{parent?: TalkChatMessage}
 *
 * @psalm-type TalkChatReminder = array{
 *     messageId: int,
 *     timestamp: int,
 *     token: string,
 *     userId: string
 * }
 *
 * @psalm-type TalkFederationInvite = array{
 *     accessToken: string,
 *     id: int,
 *     state: int,
 *     localCloudId: string,
 *     localRoomId: int,
 *     remoteAttendeeId: int,
 *     remoteServerUrl: string,
 *     remoteToken: string,
 *     roomName: string,
 *     userId: string,
 *     inviterCloudId: string,
 *     inviterDisplayName: string,
 * }
 *
 * @psalm-type TalkMatterbridgeConfigFields = array<array<string, mixed>>
 *
 * @psalm-type TalkMatterbridge = array{
 *     enabled: bool,
 *     parts: TalkMatterbridgeConfigFields,
 *     pid: int,
 * }
 *
 * @psalm-type TalkMatterbridgeProcessState = array{
 *     log: string,
 *     running: bool,
 * }
 *
 * @psalm-type TalkMatterbridgeWithProcessState = TalkMatterbridge&TalkMatterbridgeProcessState
 *
 * @psalm-type TalkParticipant = array{
 *     actorId: string,
 *     actorType: string,
 *     attendeeId: int,
 *     attendeePermissions: int,
 *     attendeePin: string,
 *     displayName: string,
 *     inCall: int,
 *     lastPing: int,
 *     participantType: int,
 *     permissions: int,
 *     roomToken: string,
 *     sessionIds: string[],
 *     status?: string,
 *     statusClearAt?: ?int,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 *     phoneNumber?: ?string,
 *     callId?: ?string,
 * }
 *
 * @psalm-type TalkPollVote = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     optionId: int,
 *  }
 *
 * @psalm-type TalkPoll = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     details?: TalkPollVote[],
 *     id: int,
 *     maxVotes: int,
 *     numVoters?: int,
 *     options: string[],
 *     question: string,
 *     resultMode: int,
 *     status: int,
 *     votedSelf?: int[],
 *     votes?: array<string, int>,
 * }
 *
 * @psalm-type TalkReaction = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     timestamp: int,
 * }
 *
 * @psalm-type TalkRoom = array{
 *     actorId: string,
 *     actorType: string,
 *     attendeeId: int,
 *     attendeePermissions: int,
 *     attendeePin: ?string,
 *     avatarVersion: string,
 *     breakoutRoomMode: int,
 *     breakoutRoomStatus: int,
 *     callFlag: int,
 *     callPermissions: int,
 *     callRecording: int,
 *     callStartTime: int,
 *     canDeleteConversation: bool,
 *     canEnableSIP: bool,
 *     canLeaveConversation: bool,
 *     canStartCall: bool,
 *     defaultPermissions: int,
 *     description: string,
 *     displayName: string,
 *     hasCall: bool,
 *     hasPassword: bool,
 *     id: int,
 *     isCustomAvatar: bool,
 *     isFavorite: bool,
 *     lastActivity: int,
 *     lastCommonReadMessage: int,
 *     lastMessage: TalkChatMessage|array<empty>,
 *     lastPing: int,
 *     lastReadMessage: int,
 *     listable: int,
 *     lobbyState: int,
 *     lobbyTimer: int,
 *     messageExpiration: int,
 *     name: string,
 *     notificationCalls: int,
 *     notificationLevel: int,
 *     objectId: string,
 *     objectType: string,
 *     participantFlags: int,
 *     participantType: int,
 *     permissions: int,
 *     readOnly: int,
 *     recordingConsent: int,
 *     sessionId: string,
 *     sipEnabled: int,
 *     status?: string,
 *     statusClearAt?: ?int,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 *     token: string,
 *     type: int,
 *     unreadMention: bool,
 *     unreadMentionDirect: bool,
 *     unreadMessages: int,
 * }
 *
 * @psalm-type TalkSignalingSession = array{
 *     inCall: int,
 *     lastPing: int,
 *     participantPermissions: int,
 *     roomId: int,
 *     sessionId: string,
 *     userId: string,
 * }
 *
 * @psalm-type TalkSignalingSettings = array{
 *     helloAuthParams: array{
 *         "1.0": array{
 *             userid: ?string,
 *             ticket: string,
 *         },
 *         "2.0": array{
 *             token: string,
 *         },
 *     },
 *     hideWarning: bool,
 *     server: string,
 *     signalingMode: string,
 *     sipDialinInfo: string,
 *     stunservers: array{urls: string[]}[],
 *     ticket: string,
 *     turnservers: array{urls: string[], username: string, credential: mixed}[],
 *     userId: ?string,
 * }
 *
 * @psalm-type TalkCapabilities = array{
 *     features: string[],
 *     config: array{
 *         attachments: array{
 *             allowed: bool,
 *             folder?: string,
 *         },
 *         call: array{
 *             enabled: bool,
 *             breakout-rooms: bool,
 *             recording: bool,
 *             recording-consent: int,
 *             supported-reactions: string[],
 *             predefined-backgrounds: string[],
 *             can-upload-background: bool,
 *             sip-enabled: bool,
 *             sip-dialout-enabled: bool,
 *             can-enable-sip: bool,
 *         },
 *         chat: array{
 *             max-length: int,
 *             read-privacy: int,
 *             has-translation-providers: bool,
 *             typing-privacy: int,
 *         },
 *         conversations: array{
 *             can-create: bool,
 *         },
 *         previews: array{
 *             max-gif-size: int,
 *         },
 *         signaling: array{
 *             session-ping-limit: int,
 *             hello-v2-token-key?: string,
 *         },
 *     },
 *     version: string,
 * }
 */
class ResponseDefinitions {
}
