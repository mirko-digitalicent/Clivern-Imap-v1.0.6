<?php

/*
 * This file is part of the Imap PHP package.
 * (c) Clivern <hello@clivern.com>
 */

namespace Clivern\Imap;

use Clivern\Imap\Core\Connection;
use Clivern\Imap\Core\Exception\FolderNotExistException;
use Clivern\Imap\Core\Message;
use Clivern\Imap\Core\Message\Action;
use Clivern\Imap\Core\Message\Attachment;
use Clivern\Imap\Core\Message\Body;
use Clivern\Imap\Core\Message\Header;
use Clivern\Imap\Core\MessageIterator;
use Clivern\Imap\Core\Search;

/**
 * MailBox Class.
 */
class MailBox
{
    /**
     * @var string
     */
    protected $folder;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $folders = [];

    /**
     * Constructor.
     *
     * @param Connection $connection IMAP connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Set Folder Name.
     *
     * @param string $folder
     *
     * @return MailBox
     */
    public function setFolder($folder)
    {
        if (!\in_array($folder, $this->getFolders(), true)) {
            throw new FolderNotExistException($folder);
        }

        $this->folder = $folder;

        return $this;
    }

    /**
     * Get Folder name.
     *
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Get number of messages in this mailbox.
     *
     * @return int
     */
    public function count()
    {
        $this->connection->survive($this->folder);

        return imap_num_msg($this->connection->getStream());
    }

    /**
     * Get message ids.
     *
     * @param Search $search
     * @param bool   $reverse
     *
     * @return MessageIterator
     */
    public function getMessages(Search $search = null, bool $reverse = true)
    {
        $this->connection->survive($this->folder);
        $query = ($search) ? (string) $search : 'ALL';
        $message_numbers = imap_search($this->connection->getStream(), $query, \SE_UID);

        if (false === $message_numbers) {
            // imap_search can also return false
            $message_numbers = [];
        }

        if ($reverse) {
            // Iterate over messages, the most recent item comes first
            $message_numbers = array_reverse($message_numbers);
        }

        return new MessageIterator($this->connection, $message_numbers);
    }

    /**
     * Get a message by message number or uid.
     *
     * @param bool|int $message_number
     * @param bool|int $message_uid
     *
     * @return Message
     */
    public function getMessage($message_number = false, $message_uid = false)
    {
        $this->connection->survive($this->folder);

        $message = new Message(
            $this->connection,
            new Header($this->connection),
            new Action($this->connection),
            new Attachment($this->connection),
            new Body($this->connection)
        );

        if (false === $message_number) {
            return $message->setUid($message_uid)->config();
        }

        return $message->setMsgNo($message_number)->config();
    }

    /**
     * Delete all messages marked for deletion.
     *
     * @return bool
     */
    public function expunge()
    {
        $this->connection->survive($this->folder);

        return (bool) imap_expunge($this->connection->getStream());
    }

    /**
     * Get Folders List.
     *
     * @return array
     */
    public function getFolders()
    {
        if (!empty($this->folders)) {
            return $this->folders;
        }

        $this->folders = imap_getmailboxes($this->connection->getStream(), '{' . $this->connection->getServer() . '}', '*');

        foreach ($this->folders as $key => $folder) {
            $this->folders[$key] = str_replace('{' . $this->connection->getServer() . '}', '', $folder->name);
        }

        return $this->folders;
    }
}
