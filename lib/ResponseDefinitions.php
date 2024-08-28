<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

/**
 * @psalm-type TalkActorTypes = 'users'|'groups'|'guests'|'emails'|'circles'|'bridged'|'bots'|'federated_users'|'phones'
 * @psalm-type TalkParticipantTypes = 1|2|3|4|5|6
 * @psalm-type TalkRoomTypes = 1|2|3|4|5|6
 * @psalm-type TalkCallFlags = int<0, 15>
 * @psalm-type TalkPermissions = int<0, 255>
 *
 * @psalm-type TalkBan = array{
 *     id: int<1, max>,
 *     moderatorActorType: TalkActorTypes,
 *     moderatorActorId: non-empty-string,
 *     moderatorDisplayName: non-empty-string,
 *     bannedActorType: 'guests'|'users'|'ip',
 *     bannedActorId: non-empty-string,
 *     bannedDisplayName: non-empty-string,
 *     bannedTime: int<0, max>,
 *     internalNote: string,
 * }
 *
 * @psalm-type TalkBot = array{
 *     description: ?string,
 *     id: int<1, max>,
 *     name: non-empty-string,
 *     state: 0|1|2,
 * }
 *
 * @psalm-type TalkBotWithDetails = TalkBot&array{
 *     error_count: int<0, max>,
 *     features: 0|1|2|3,
 *     last_error_date: int<0, max>,
 *     last_error_message: string,
 *     url: non-empty-string,
 *     url_hash: non-empty-string,
 * }
 *
 * @psalm-type TalkBotWithDetailsAndSecret = TalkBotWithDetails&array{
 *     secret: non-empty-string,
 * }
 *
 * @psalm-type TalkCallPeer = array{
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     displayName: non-empty-string,
 *     lastPing: int<0, max>,
 *     sessionId: non-empty-string,
 *     token: non-empty-string,
 * }
 *
 * @psalm-type TalkChatMentionSuggestion = array{
 *     id: non-empty-string,
 *     label: non-empty-string,
 *     source: non-empty-string,
 *     mentionId: non-empty-string,
 *     details?: non-empty-string,
 *     status?: 'online'|'away'|'dnd'|'busy'|'offline'|'invisible',
 *     statusClearAt?: ?int<0, max>,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 * }
 *
 * @psalm-type TalkRichObjectParameter = array{
 *     type: non-empty-string,
 *     id: non-empty-string,
 *     name: non-empty-string,
 *     server?: non-empty-string,
 *     link?: non-empty-string,
 *     'call-type'?: 'one2one'|'group'|'public',
 *     'icon-url'?: non-empty-string,
 *     'message-id'?: non-empty-string,
 *     boardname?: non-empty-string,
 *     stackname?: non-empty-string,
 *     size?: non-empty-string,
 *     path?: non-empty-string,
 *     mimetype?: non-empty-string,
 *     'preview-available'?: 'yes'|'no',
 *     mtime?: non-empty-string,
 *     latitude?: non-empty-string,
 *     longitude?: non-empty-string,
 *     description?: non-empty-string,
 *     thumb?: non-empty-string,
 *     website?: non-empty-string,
 *     visibility?: '0'|'1',
 *     assignable?: '0'|'1',
 *     conversation?: non-empty-string,
 *     etag?: non-empty-string,
 *     permissions?: non-empty-string,
 *     width?: non-empty-string,
 *     height?: non-empty-string,
 *     blurhash?: non-empty-string,
 * }
 *
 * @psalm-type TalkBaseMessage = array{
 *     actorDisplayName: non-empty-string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     expirationTimestamp: int<0, max>,
 *     message: non-empty-string,
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     messageType: string,
 *     systemMessage: non-empty-string,
 *  }
 *
 * @psalm-type TalkChatMessage = TalkBaseMessage&array{
 *     deleted?: true,
 *     id: int<1, max>,
 *     isReplyable: bool,
 *     markdown: bool,
 *     reactions: array<string, integer>|\stdClass,
 *     reactionsSelf?: non-empty-string[],
 *     referenceId: string,
 *     timestamp: int<0, max>,
 *     token: non-empty-string,
 *     lastEditActorDisplayName?: non-empty-string,
 *     lastEditActorId?: non-empty-string,
 *     lastEditActorType?: TalkActorTypes,
 *     lastEditTimestamp?: int<0, max>,
 *     silent?: bool,
 * }
 *
 * @psalm-type TalkChatProxyMessage = TalkBaseMessage
 *
 * @psalm-type TalkRoomLastMessage = TalkChatMessage|TalkChatProxyMessage
 *
 * @psalm-type TalkDeletedChatMessage = array{
 *     id: int,
 *     deleted: true,
 * }
 *
 * @psalm-type TalkChatMessageWithParent = TalkChatMessage&array{parent?: TalkChatMessage|TalkDeletedChatMessage}
 *
 * @psalm-type TalkChatReminder = array{
 *     messageId: int<1, max>,
 *     timestamp: int<0, max>,
 *     token: non-empty-string,
 *     userId: non-empty-string
 * }
 *
 * @psalm-type TalkFederationInvite = array{
 *     id: int<1, max>,
 *     state: 0|1,
 *     localCloudId: non-empty-string,
 *     localToken: non-empty-string,
 *     remoteAttendeeId: int<1, max>,
 *     remoteServerUrl: non-empty-string,
 *     remoteToken: non-empty-string,
 *     roomName: non-empty-string,
 *     userId: non-empty-string,
 *     inviterCloudId: non-empty-string,
 *     inviterDisplayName: non-empty-string,
 * }
 *
 * @psalm-type TalkMatterbridgeConfigFields = array<array<string, mixed>>
 *
 * @psalm-type TalkMatterbridge = array{
 *     enabled: bool,
 *     parts: TalkMatterbridgeConfigFields,
 *     pid: int<1, max>,
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
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     attendeeId: int<1, max>,
 *     attendeePermissions: TalkPermissions,
 *     attendeePin: string,
 *     displayName: non-empty-string,
 *     inCall: TalkCallFlags,
 *     lastPing: int<0, max>,
 *     participantType: TalkParticipantTypes,
 *     permissions: TalkPermissions,
 *     roomToken: non-empty-string,
 *     sessionIds: string[],
 *     status?: 'online'|'away'|'dnd'|'busy'|'offline'|'invisible',
 *     statusClearAt?: ?int<0, max>,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 *     phoneNumber?: ?string,
 *     callId?: ?string,
 * }
 *
 * @psalm-type TalkPollVote = array{
 *     actorDisplayName: non-empty-string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     optionId: int<0, max>,
 *  }
 *
 * @psalm-type TalkPoll = array{
 *     actorDisplayName: non-empty-string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     details?: TalkPollVote[],
 *     id: int<1, max>,
 *     maxVotes: int<0, max>,
 *     numVoters?: int<0, max>,
 *     options: non-empty-string[],
 *     question: non-empty-string,
 *     resultMode: 0|1,
 *     status: 0|1,
 *     votedSelf?: int[],
 *     votes?: array<string, int>,
 * }
 *
 * @psalm-type TalkReaction = array{
 *     actorDisplayName: non-empty-string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     timestamp: int<0, max>,
 * }
 *
 * @psalm-type TalkRoom = array{
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     attendeeId: int<1, max>,
 *     attendeePermissions: TalkPermissions,
 *     attendeePin: ?string,
 *     avatarVersion: string,
 *     breakoutRoomMode: 0|1|2|3,
 *     breakoutRoomStatus: 0|1|2,
 *     callFlag: TalkCallFlags,
 *     callPermissions: TalkPermissions,
 *     callRecording: int<0, 5>,
 *     callStartTime: int<0, max>,
 *     canDeleteConversation: bool,
 *     canEnableSIP: bool,
 *     canLeaveConversation: bool,
 *     canStartCall: bool,
 *     defaultPermissions: TalkPermissions,
 *     description: string,
 *     displayName: non-empty-string,
 *     hasCall: bool,
 *     hasPassword: bool,
 *     id: int<1, max>,
 *     isCustomAvatar: bool,
 *     isFavorite: bool,
 *     lastActivity: int<0, max>,
 *     lastCommonReadMessage: int<0, max>,
 *     lastMessage: TalkRoomLastMessage|array<empty>,
 *     lastPing: int<0, max>,
 *     lastReadMessage: int<0, max>,
 *     listable: 0|1|2,
 *     lobbyState: 0|1,
 *     lobbyTimer: int<0, max>,
 *     mentionPermissions: 0|1,
 *     messageExpiration: int<0, max>,
 *     name: non-empty-string,
 *     notificationCalls: 0|1,
 *     notificationLevel: 0|1|2|3,
 *     objectId: string,
 *     objectType: string,
 *     participantFlags: TalkCallFlags,
 *     participantType: TalkParticipantTypes,
 *     permissions: TalkPermissions,
 *     readOnly: 0|1,
 *     recordingConsent: 0|1|2,
 *     remoteServer?: non-empty-string,
 *     remoteToken?: non-empty-string,
 *     sessionId: string,
 *     sipEnabled: 0|1|2,
 *     status?: 'online'|'away'|'dnd'|'busy'|'offline'|'invisible',
 *     statusClearAt?: ?int<0, max>,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 *     token: non-empty-string,
 *     type: TalkRoomTypes,
 *     unreadMention: bool,
 *     unreadMentionDirect: bool,
 *     unreadMessages: int<0, max>,
 * }
 *
 * @psalm-type TalkSignalingSession = array{
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     inCall: TalkCallFlags,
 *     lastPing: int<0, max>,
 *     participantPermissions: TalkPermissions,
 *     roomId: int<1, max>,
 *     sessionId: non-empty-string,
 *     userId: string,
 * }
 *
 * @psalm-type TalkSignalingSettings = array{
 *     federation: array{
 *         server: string,
 *         nextcloudServer: string,
 *         helloAuthParams: array{
 *             token: string,
 *         },
 *         roomId: string,
 *     }|array<empty>,
 *     helloAuthParams: array{
 *         "1.0": array{
 *             userid: ?string,
 *             ticket: non-empty-string,
 *         },
 *         "2.0": array{
 *             token: non-empty-string,
 *         },
 *     },
 *     hideWarning: bool,
 *     server: non-empty-string,
 *     signalingMode: 'internal'|'external'|'conversation_cluster',
 *     sipDialinInfo: string,
 *     stunservers: array{urls: non-empty-string[]}[],
 *     ticket: non-empty-string,
 *     turnservers: array{urls: non-empty-string[], username: non-empty-string, credential: mixed}[],
 *     userId: ?string,
 * }
 *
 * @psalm-type TalkCapabilities = array{
 *     features: string[],
 *     features-local: string[],
 *     config: array{
 *         attachments: array{
 *             allowed: bool,
 *             folder?: string,
 *         },
 *         call: array{
 *             enabled: bool,
 *             breakout-rooms: bool,
 *             recording: bool,
 *             recording-consent: 0|1|2,
 *             supported-reactions: string[],
 *             predefined-backgrounds: string[],
 *             can-upload-background: bool,
 *             sip-enabled: bool,
 *             sip-dialout-enabled: bool,
 *             can-enable-sip: bool,
 *         },
 *         chat: array{
 *             max-length: 32000,
 *             read-privacy: 0|1,
 *             has-translation-providers: bool,
 *             typing-privacy: 0|1,
 *         },
 *         conversations: array{
 *             can-create: bool,
 *         },
 *         federation: array{
 *             enabled: bool,
 *             incoming-enabled: bool,
 *             outgoing-enabled: bool,
 *             only-trusted-servers: bool,
 *         },
 *         previews: array{
 *             max-gif-size: int<0, max>,
 *         },
 *         signaling: array{
 *             session-ping-limit: int<0, max>,
 *             hello-v2-token-key?: non-empty-string,
 *         },
 *     },
 *     config-local: array<string, string[]>,
 *     version: non-empty-string,
 * }
 */
class ResponseDefinitions {
}
