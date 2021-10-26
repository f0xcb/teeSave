<?php

namespace App;

use DateTime;

class Message
{
    private int $id;
    private DateTime $dateTime;
    private string $views;
    private string $text;
    private string $messageUrl;

    public function __construct(
        int      $id,
        DateTime $dateTime,
        string   $views,
        string   $text
    )
    {
        $this->id = $id;
        $this->dateTime = $dateTime;
        $this->views = $views;
        $this->text = $text;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function getViews(): string
    {
        return $this->views;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getMessageUrl(): string
    {
        return $this->messageUrl;
    }
}