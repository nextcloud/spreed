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
 * @psalm-type SpreedRoomShare = array{
 *     access_token: string,
 *     id: int,
 *     remote_id: string,
 *     remote_server: ?string,
 *     remote_token: ?string,
 *     room_id: int,
 *     user_id: string,
 * }
 *
 * @psalm-type SpreedReaction = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     timestamp: int,
 * }
 *
 * @psalm-type SpreedPollVote = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     optionId: int,
 * }
 *
 * @psalm-type SpreedPoll = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     details?: SpreedPollVote[],
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
 * @psalm-type SpreedPollWithRoomId = SpreedPoll&array{
 *     roomId: string,
 * }
 *
 * @psalm-type SpreedMessage = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     deleted?: true,
 *     expirationTimestamp: int,
 *     id: int,
 *     isReplyable: bool,
 *     message: string,
 *     messageParameters: array<string, mixed>,
 *     messageType: string,
 *     reactions: array<string, integer>|\stdClass,
 *     referenceId: string,
 *     systemMessage: string,
 *     threadId: int,
 *     timestamp: int,
 *     token: string,
 * }
 *
 * @psalm-type SpreedMessageWithParent = SpreedMessage&array{parent?: SpreedMessage}
 *
 * @psalm-type SpreedRoom = array{
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
 *     lastMessage: SpreedMessage|array<empty>,
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
 * @psalm-type SpreedRoomParticipant = array{
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
 * }
 *
 * @psalm-type SpreedCallPeer = array{
 *     actorId: string,
 *     actorType: string,
 *     displayName: string,
 *     lastPing: int,
 *     sessionId: string,
 *     token: string,
 * }
 *
 * @psalm-type SpreedMention = array{
 *     id: string,
 *     label: string,
 *     source: string,
 *     status: ?string,
 *     statusClearAt: ?int,
 *     statusIcon: ?string,
 *     statusMessage: ?string,
 * }
 *
 * @psalm-type SpreedMatterbridgeParts = array<array<string, mixed>>
 *
 * @psalm-type SpreedMatterbridge = array{
 *     enabled: bool,
 *     parts: SpreedMatterbridgeParts,
 *     pid: int,
 * }
 *
 * @psalm-type SpreedMatterbridgeProcessState = array{
 *     log: string,
 *     running: bool,
 * }
 *
 * @psalm-type SpreedMatterbridgeWithProcessState = SpreedMatterbridge&SpreedMatterbridgeProcessState
 *
 * @psalm-type SpreedSignalingSettings = array{
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
 * @psalm-type SpreedSignalingUsers = array{
 *     inCall: int,
 *     lastPing: int,
 *     participantPermissions: int,
 *     roomId: int,
 *     sessionId: string,
 *     userId: string,
 * }
 *
 * @psalm-type SpreedBot = array{
 *     id: int,
 *     name: string,
 *     description: ?string,
 *     state: int,
 * }
 *
 * @psalm-type SpreedAdminBot = array{
 *     description: ?string,
 *     error_count: int,
 *     features: int,
 *     id: int,
 *     last_error_date: int,
 *     last_error_message: string,
 *     name: string,
 *     state: int,
 *     url: string,
 *     url_hash: string,
 * }
 *
 * @psalm-type SpreedAdminBotWithSecret = SpreedAdminBot&array{
 *     secret: string,
 * }
 *
 * @psalm-type SpreedReminder = array{
 *     messageId: int,
 *     timestamp: int,
 *     token: string,
 *     userId: string
 * }
 */
class ResponseDefinitions {
}
