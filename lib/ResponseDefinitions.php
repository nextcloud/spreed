<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

/**
 * @psalm-type TalkActorTypes = 'users'|'groups'|'guests'|'emails'|'circles'|'bridged'|'bots'|'federated_users'|'phones'
 *
 * @psalm-type TalkBan = array{
 *     id: int,
 *     moderatorActorType: string,
 *     moderatorActorId: string,
 *     moderatorDisplayName: string,
 *     bannedActorType: string,
 *     bannedActorId: string,
 *     bannedDisplayName: string,
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
 *     details?: string,
 *     status?: string,
 *     statusClearAt?: ?int,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
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
 *     blurhash?: string,
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
 *     reactionsSelf?: list<string>,
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
 * @psalm-type TalkDeletedChatMessage = array{
 *     id: int,
 *     deleted: true,
 * }
 *
 * @psalm-type TalkChatMessageWithParent = TalkChatMessage&array{parent?: TalkChatMessage|TalkDeletedChatMessage}
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
 * @psalm-type TalkMatterbridgeConfigFields = list<array<string, mixed>>
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
 *     invitedActorId?: string,
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
 *     sessionIds: list<string>,
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
 * @psalm-type TalkPollDraft = array{
 *     actorDisplayName: string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     id: int<1, max>,
 *     maxVotes: int<0, max>,
 *     options: list<string>,
 *     question: non-empty-string,
 *     resultMode: 0|1,
 *     status: 0|1|2,
 * }
 *
 * @psalm-type TalkPoll = TalkPollDraft&array{
 *     details?: list<TalkPollVote>,
 *     numVoters?: int<0, max>,
 *     votedSelf?: list<int>,
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
 * @psalm-type TalkInvitationList = array{
 *     users?: list<string>,
 *     federated_users?: list<string>,
 *     groups?: list<string>,
 *     emails?: list<string>,
 *     phones?: list<string>,
 *     teams?: list<string>,
 * }
 *
 * @psalm-type TalkRoom = array{
 *     actorId: string,
 *     invitedActorId?: string,
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
 *     lastMessage?: TalkRoomLastMessage,
 *     lastPing: int,
 *     lastReadMessage: int,
 *     listable: int,
 *     lobbyState: int,
 *     lobbyTimer: int,
 *     mentionPermissions: int,
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
 *     remoteServer?: string,
 *     remoteToken?: string,
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
 *     isArchived: bool,
 *     // Required capability: `important-conversations`
 *     isImportant: bool,
 * }
 *
 * @psalm-type TalkRoomWithInvalidInvitations = TalkRoom&array{
 *     invalidParticipants: TalkInvitationList,
 * }
 *
 * @psalm-type TalkSignalingSession = array{
 *     actorId: string,
 *     actorType: string,
 *     inCall: int,
 *     lastPing: int,
 *     participantPermissions: int,
 *     roomId: int,
 *     sessionId: string,
 *     userId: string,
 * }
 *
 * @psalm-type TalkSignalingFederationSettings = array{
 *     server: string,
 *     nextcloudServer: string,
 *     helloAuthParams: array{
 *         token: string,
 *     },
 *     roomId: string,
 * }
 *
 * @psalm-type TalkSignalingSettings = array{
 *     federation: TalkSignalingFederationSettings|null,
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
 *     stunservers: list<array{urls: list<string>}>,
 *     ticket: string,
 *     turnservers: list<array{urls: list<string>, username: string, credential: mixed}>,
 *     userId: ?string,
 * }
 *
 * @psalm-type TalkCapabilities = array{
 *     features: list<string>,
 *     features-local: list<string>,
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
 *             supported-reactions: list<string>,
 *             // List of file names relative to the spreed/img/backgrounds/ web path, e.g. `2_home.jpg`
 *             predefined-backgrounds: list<string>,
 *              // List of file paths relative to the server web root with leading slash, e.g. `/apps/spreed/img/backgrounds/2_home.jpg`
 *             predefined-backgrounds-v2: list<string>,
 *             can-upload-background: bool,
 *             sip-enabled: bool,
 *             sip-dialout-enabled: bool,
 *             can-enable-sip: bool,
 *             start-without-media: bool,
 *             max-duration: int,
 *             blur-virtual-background: bool,
 *             end-to-end-encryption: bool,
 *         },
 *         chat: array{
 *             max-length: int,
 *             read-privacy: int,
 *             has-translation-providers: bool,
 *             has-translation-task-providers: bool,
 *             typing-privacy: int,
 *             summary-threshold: positive-int,
 *         },
 *         conversations: array{
 *             can-create: bool,
 *             force-passwords: bool,
 *             list-style: 'two-lines'|'compact',
 *             description-length: positive-int,
 *             retention-event: non-negative-int,
 *             retention-phone: non-negative-int,
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
 *     config-local: array<string, list<string>>,
 *     version: string,
 * }
 */
class ResponseDefinitions {
}
