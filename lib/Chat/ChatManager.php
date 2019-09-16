<?php
declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Chat;

use OCA\Spreed\Participant;
use OCA\Spreed\Room;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Basic polling chat manager.
 *
 * sendMessage() saves a comment using the ICommentsManager, while
 * receiveMessages() tries to read comments from ICommentsManager (with a little
 * wait between reads) until comments are found or until the timeout expires.
 *
 * When a message is saved the mentioned users are notified as needed, and
 * pending notifications are removed if the messages are deleted.
 */
class ChatManager
{

    /** @var CommentsManager|ICommentsManager */
    private $commentsManager;
    /** @var EventDispatcherInterface */
    private $dispatcher;
    /** @var Notifier */
    private $notifier;
    /** @var ITimeFactory */
    protected $timeFactory;

    private $db;

    public function __construct(
        CommentsManager $commentsManager,
        EventDispatcherInterface $dispatcher,
        Notifier $notifier,
        ITimeFactory $timeFactory,
        IDBConnection $db
    ) {
        $this->commentsManager = $commentsManager;
        $this->dispatcher      = $dispatcher;
        $this->notifier        = $notifier;
        $this->timeFactory     = $timeFactory;
        $this->db              = $db;
    }

    /**
     * Sends a new message to the given chat.
     *
     * @param  Room  $chat
     * @param  string  $actorType
     * @param  string  $actorId
     * @param  string  $message
     * @param  \DateTime  $creationDateTime
     * @param  bool  $sendNotifications
     *
     * @return IComment
     */
    public function addSystemMessage(
        Room $chat,
        string $actorType,
        string $actorId,
        string $message,
        \DateTime $creationDateTime,
        bool $sendNotifications
    ): IComment {
        $comment = $this->commentsManager->create(
            $actorType,
            $actorId,
            'chat',
            (string) $chat->getId()
        );
        $comment->setMessage($message);
        $comment->setCreationDateTime($creationDateTime);
        $comment->setVerb('system');
        try {
            $this->commentsManager->save($comment);

            // Update last_message
            $chat->setLastMessage($comment);

            if ($sendNotifications) {
                $this->notifier->notifyOtherParticipant($chat, $comment, []);
            }

            $this->dispatcher->dispatch(
                self::class.'::sendSystemMessage',
                new GenericEvent($chat, [
                    'comment' => $comment,
                ])
            );
        } catch (NotFoundException $e) {
        }

        return $comment;
    }

    /**
     * Sends a new message to the given chat.
     *
     * @param  Room  $chat
     * @param  string  $message
     *
     * @return IComment
     */
    public function addChangelogMessage(Room $chat, string $message): IComment
    {
        $comment = $this->commentsManager->create(
            'guests',
            'changelog',
            'chat',
            (string) $chat->getId()
        );
        $comment->setMessage($message);
        $comment->setCreationDateTime($this->timeFactory->getDateTime());
        $comment->setVerb('comment'); // Has to be comment, so it counts as unread message

        try {
            $this->commentsManager->save($comment);

            // Update last_message
            $chat->setLastMessage($comment);

            $this->dispatcher->dispatch(
                self::class.'::sendSystemMessage',
                new GenericEvent($chat, [
                    'comment' => $comment,
                ])
            );
        } catch (NotFoundException $e) {
        }

        return $comment;
    }

    /**
     * Sends a new message to the given chat.
     *
     * @param  Room  $chat
     * @param  Participant  $participant
     * @param  string  $actorType
     * @param  string  $actorId
     * @param  string  $message
     * @param  \DateTime  $creationDateTime
     *
     * @return IComment
     */
    public function sendMessage(
        Room $chat,
        Participant $participant,
        string $actorType,
        string $actorId,
        string $message,
        \DateTime $creationDateTime
    ): IComment {
        $comment = $this->commentsManager->create(
            $actorType,
            $actorId,
            'chat',
            (string) $chat->getId()
        );
        $comment->setMessage($message);
        $comment->setCreationDateTime($creationDateTime);
        // A verb ('comment', 'like'...) must be provided to be able to save a
        // comment
        $comment->setVerb('comment');

        $this->dispatcher->dispatch(
            self::class.'::preSendMessage',
            new GenericEvent($chat, [
                'comment'     => $comment,
                'room'        => $chat,
                'participant' => $participant,
            ])
        );

        try {
            $this->commentsManager->save($comment);

            // Update last_message
            $chat->setLastMessage($comment);

            $mentionedUsers = $this->notifier->virtuallyMentionEveryone(
                $chat,
                $comment
            );

            if (! empty($mentionedUsers)) {
                $chat->markUsersAsMentioned($mentionedUsers, $creationDateTime);
            }

            // User was not mentioned, send a normal notification
            $this->notifier->notifyOtherParticipant(
                $chat,
                $comment,
                $mentionedUsers
            );

            $this->dispatcher->dispatch(
                self::class.'::sendMessage',
                new GenericEvent($chat, [
                    'comment'     => $comment,
                    'room'        => $chat,
                    'participant' => $participant,
                ])
            );
        } catch (NotFoundException $e) {
        }

        return $comment;
    }

    public function getUnreadMarker(Room $chat, IUser $user): \DateTime
    {
        $marker = $this->commentsManager->getReadMark(
            'chat',
            $chat->getId(),
            $user
        );
        if ($marker === null) {
            $marker = $this->timeFactory->getDateTime('2000-01-01');
        }

        return $marker;
    }

    public function getUnreadCount(Room $chat, \DateTime $unreadSince): int
    {
        return $this->commentsManager->getNumberOfCommentsForObject(
            'chat',
            $chat->getId(),
            $unreadSince,
            'comment'
        );
    }

    /**
     * Receive the history of a chat
     *
     * @param  Room  $chat
     * @param  int  $offset  Last known message id
     * @param  int  $limit
     *
     * @return IComment[] the messages found (only the id, actor type and id,
     *         creation date and message are relevant), or an empty array if the
     *         timeout expired.
     */
    public function getHistory(Room $chat, $offset, $limit): array
    {
        return $this->commentsManager->getForObjectSince(
            'chat',
            (string) $chat->getId(),
            $offset,
            'desc',
            $limit
        );
    }

    /**
     * If there are currently no messages the response will not be sent
     * immediately. Instead, HTTP connection will be kept open waiting for new
     * messages to arrive and, when they do, then the response will be sent. The
     * connection will not be kept open indefinitely, though; the number of
     * seconds to wait for new messages to arrive can be set using the timeout
     * parameter; the default timeout is 30 seconds, maximum timeout is 60
     * seconds. If the timeout ends a successful but empty response will be
     * sent.
     *
     * @param  Room  $chat
     * @param  int  $offset  Last known message id
     * @param  int  $limit
     * @param  int  $timeout
     * @param  IUser|null  $user
     *
     * @return IComment[] the messages found (only the id, actor type and id,
     *         creation date and message are relevant), or an empty array if the
     *         timeout expired.
     */
    public function waitForNewMessages(
        Room $chat,
        int $offset,
        int $limit,
        int $timeout,
        ?IUser $user
    ): array {
        if ($user instanceof IUser) {
            $this->notifier->markMentionNotificationsRead(
                $chat,
                $user->getUID()
            );
        }
        $elapsedTime = 0;

        $comments = $this->commentsManager->getForObjectSince(
            'chat',
            (string) $chat->getId(),
            $offset,
            'asc',
            $limit
        );

        if ($user instanceof IUser) {
            $this->commentsManager->setReadMark(
                'chat',
                (string) $chat->getId(),
                $this->timeFactory->getDateTime(),
                $user
            );
        }

        while (empty($comments) && $elapsedTime < $timeout) {
            sleep(1);
            $elapsedTime++;

            $comments = $this->commentsManager->getForObjectSince(
                'chat',
                (string) $chat->getId(),
                $offset,
                'asc',
                $limit
            );
        }

        return $comments;
    }

    /**
     * Deletes all the messages for the given chat.
     *
     * @param  Room  $chat
     */
    public function deleteMessages(Room $chat): void
    {
        $this->commentsManager->deleteCommentsAtObject(
            'chat',
            (string) $chat->getId()
        );

        $this->notifier->removePendingNotificationsForRoom($chat);
    }

    /**
     * Get rooms info and latest comment per room.
     *
     * @param $roomsInfo
     *
     * @return array
     */
    public function roomsInfo($roomsInfo)
    {
        $passedRooms      = [];
        $passedRoomTokens = [];

        // Lets reformat chatroom data.
        foreach ($roomsInfo as $info) {
            $passedRooms[]      = [
                'token'        => $info['token'],
                'last_message' => $info['last_message'],
            ];
            $passedRoomTokens[] = $info['token'];
        }

        // Get info of given chat rooms.
        $qb = $this->db->getQueryBuilder();
        $qb = $qb->select('*')
            ->from('talk_rooms')
            ->where(
                $qb->expr()->in(
                    'token',
                    $qb->createNamedParameter(
                        $passedRoomTokens,
                        IQueryBuilder::PARAM_STR_ARRAY
                    )
                )
            );

        $cursor       = $qb->execute();
        $fetchedRooms = $cursor->fetchAll();
        $cursor->closeCursor();

        // Get chat rooms with changes.
        // Usually, if last_message has changed.
        $roomChanges = [];
        foreach ($fetchedRooms as $fetchedRoom) {
            foreach ($passedRooms as $passedRoom) {
                if ($fetchedRoom['token'] == $passedRoom['token']) {
                    if ($fetchedRoom['last_message']
                        !== $passedRoom['last_message']
                    ) {
                        $roomChanges[] = $fetchedRoom;
                    }
                }
            }
        }

        // Get rooms with new comment.
        $lastMessages = [];
        foreach ($roomChanges as $roomChange) {
            $lastMessages[] = (int) $roomChange['last_message'];
        }

        // Get comments of a given room.
        $qb = $this->db->getQueryBuilder();
        $qb = $qb->select('*')
            ->from('comments')
            ->where(
                $qb->expr()->in(
                    'id',
                    $qb->createNamedParameter(
                        $lastMessages,
                        IQueryBuilder::PARAM_INT_ARRAY
                    )
                )
            );

        $cursor          = $qb->execute();
        $fetchedComments = $cursor->fetchAll();
        $cursor->closeCursor();

        // Prepare response.
        $response             = [];
        $response['rooms']    = $fetchedRooms;
        $response['comments'] = [];

        // Attach latest comments.
        foreach ($fetchedComments as $comment) {
            $response['comments'][] = [
                'actor_id'   => $comment['actor_id'],
                'actor_type' => $comment['actor_type'],
                'message'    => $this->generateCommentMsg($comment),
            ];
        }

        return $response;
    }

    /**
     * Generate a formatted comment message.
     *
     * @param $comment
     *
     * @author Oozman
     * @return mixed|string
     */
    private function generateCommentMsg($comment)
    {
        if ($comment['verb'] == 'system') {
            $msg = json_decode($comment['message'], true);

            if ($msg['message'] === 'file_shared') {
                return $comment['actor_id'].' just shared a file.';
            }
        } elseif ($comment['verb'] === 'comment') {
            return $comment['message'];
        }

        return 'You got a message.';
    }

    /**
     * Get last message by an actor in room.
     *
     * @param $room
     * @param $actorId
     *
     * @author Oozman
     * @return int
     * @throws \Exception
     */
    public function getLastMessageByActorInRoom($room, $actorId)
    {
        // Get object id.
        $query  = $this->db->getQueryBuilder();
        $result = $query->select('id')
            ->from('talk_rooms')
            ->where($query->expr()
                ->eq('token', $query->createNamedParameter($room)))
            ->setMaxResults(1)
            ->execute();

        $objectId = 0;
        while ($row = $result->fetch()) {
            $objectId = $row['id'];
            break;
        }

        if ($objectId < 1) {
            return 0;
        }
        $result->closeCursor();

        // Get last comment.
        $query = $this->db->getQueryBuilder();

        $result = $query->select('id')
            ->from('comments')
            ->where($query->expr()
                ->eq('object_type', $query->createNamedParameter('chat')))
            ->andWhere($query->expr()
                ->eq('object_id', $query->createNamedParameter($objectId)))
            ->andWhere($query->expr()
                ->eq('verb', $query->createNamedParameter('comment')))
            ->andWhere($query->expr()
                ->eq('actor_type', $query->createNamedParameter('users')))
            ->andWhere($query->expr()
                ->in('actor_id', $query->createNamedParameter($actorId)))
            ->andWhere($query->expr()->gte(
                'creation_timestamp',
                $query->createNamedParameter($this->getLastMessageDateAllowed()
                    ->format('Y-m-d H:i:s'))
            ))
            ->orderBy('creation_timestamp', 'DESC')
            ->setMaxResults(1)
            ->execute();

        $lastCommentId = 0;
        while ($row = $result->fetch()) {
            $lastCommentId = $row['id'];
            break;
        }

        $result->closeCursor();

        return $lastCommentId;
    }

    /**
     * Edit comment of an actor.
     *
     * @param $commentId
     * @param $newMessage
     * @param $actorId
     * @param $room
     *
     * @author Oozman
     * @return int|null
     */
    public function editCommentOfActor($commentId, $newMessage, $actorId, $room)
    {
        $comment = null;

        // Get object id.
        $query  = $this->db->getQueryBuilder();
        $result = $query->select('id')
            ->from('talk_rooms')
            ->where($query->expr()
                ->eq('token', $query->createNamedParameter($room)))
            ->setMaxResults(1)
            ->execute();

        $objectId = 0;
        while ($row = $result->fetch()) {
            $objectId = $row['id'];
            break;
        }

        if ($objectId < 1) {
            return $comment;
        }
        $result->closeCursor();

        // Get comment.
        $query  = $this->db->getQueryBuilder();
        $result = $query->select('id')
            ->from('comments')
            ->where($query->expr()
                ->eq('id', $query->createNamedParameter($commentId)))
            ->andWhere($query->expr()
                ->eq('object_type', $query->createNamedParameter('chat')))
            ->andWhere($query->expr()
                ->eq('object_id', $query->createNamedParameter($objectId)))
            ->andWhere($query->expr()
                ->eq('verb', $query->createNamedParameter('comment')))
            ->andWhere($query->expr()
                ->eq('actor_type', $query->createNamedParameter('users')))
            ->andWhere($query->expr()
                ->in('actor_id', $query->createNamedParameter($actorId)))
            ->orderBy('creation_timestamp', 'DESC')
            ->setMaxResults(1)
            ->execute();

        while ($row = $result->fetch()) {
            $comment = $row;
            break;
        }

        $result->closeCursor();

        // Update comment.
        $query = $this->db->getQueryBuilder();

        $result = $query->update('comments')
            ->set('message', $query->createNamedParameter($newMessage))
            ->where('id = :id')
            ->setParameter(':id', $comment['id'])
            ->execute();

        return $result;
    }

    /**
     * Get last message date allowed to be edited, in minutes.
     *
     * @param  int  $interval
     *
     * @author Oozman
     * @return \DateTime
     * @throws \Exception
     */
    public function getLastMessageDateAllowed($interval = 15)
    {
        $creationDateTime = $this->timeFactory->getDateTime(
            'now',
            new \DateTimeZone('UTC')
        );

        return $creationDateTime->sub(new \DateInterval('P'.$interval.'M'));
    }
}
