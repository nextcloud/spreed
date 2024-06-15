<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

/**
 * @psalm-type TalkBan = array{
 *     id: int,
 *     actorType: string,
 *     actorId: string,
 *     bannedType: string,
 *     bannedId: string,
 *     bannedTime: int,
 *     internalNote: string,
 * }
 *
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
 *     mentionId: string,
 *     status: ?string,
 *     statusClearAt: ?int,
 *     statusIcon: ?string,
 *     statusMessage: ?string,
 * }
 *
 * @psalm-type TalkRichObjectParameter = array{
 *     type: string,
 *     id: string,
 *     name: string,
 *     server?: string,
 *     link?: string,
 *     'call-type'?: 'one2one'|'group'|'public',
 *     'icon-url'?: string,
 *     'message-id'?: string,
 *     boardname?: string,
 *     stackname?: string,
 *     size?: string,
 *     path?: string,
 *     mimetype?: string,
 *     'preview-available'?: 'yes'|'no',
 *     mtime?: string,
 *     latitude?: string,
 *     longitude?: string,
 *     description?: string,
 *     thumb?: string,
 *     website?: string,
 *     visibility?: '0'|'1',
 *     assignable?: '0'|'1',
 *     conversation?: string,
 *     etag?: string,
 *     permissions?: string,
 *     width?: string,
 *     height?: string,
 * }
 *
 * @psalm-type TalkBaseMessage = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     expirationTimestamp: int,
 *     message: string,
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     messageType: string,
 *     systemMessage: string,
 *  }
 *
 * @psalm-type TalkChatMessage = TalkBaseMessage&array{
 *     deleted?: true,
 *     id: int,
 *     isReplyable: bool,
 *     markdown: bool,
 *     reactions: array<string, integer>|\stdClass,
 *     reactionsSelf?: string[],
 *     referenceId: string,
 *     timestamp: int,
 *     token: string,
 *     lastEditActorDisplayName?: string,
 *     lastEditActorId?: string,
 *     lastEditActorType?: string,
 *     lastEditTimestamp?: int,
 *     silent?: bool,
 * }
 *
 * @psalm-type TalkChatProxyMessage = TalkBaseMessage
 *
 * @psalm-type TalkRoomLastMessage = TalkChatMessage|TalkChatProxyMessage
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
 *     id: int,
 *     state: int,
 *     localCloudId: string,
 *     localToken: string,
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
 *     lastMessage: TalkRoomLastMessage|array<empty>,
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
 *         federation: array{
 *             enabled: bool,
 *             incoming-enabled: bool,
 *             outgoing-enabled: bool,
 *             only-trusted-servers: bool,
 *         },
 *         previews: array{
 *             max-gif-size: int,
 *         },
 *         signaling: array{
 *             session-ping-limit: int,
 *             hello-v2-token-key?: string,
 *         },
 *     },
 *     config-local: array<string, string[]>,
 *     version: string,
 * }
 */
class ResponseDefinitions {
}
