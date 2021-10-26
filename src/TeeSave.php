<?php

namespace App;

use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use PDO;
use PDOException;

class TeeSave
{
    private string $rawContent;
    private Client $client;
    private Normalizer $normalizer;
    private int $retryOnEagerLoad = 1;
    private string $username;
    private array $messages = [];
    private array $info;
    private array $channel_info_patterns = [
        'name' => '/<div class="tgme_header_title">(.*?)<\/div>/is',
        'memberCount' => '/<div class="tgme_header_counter">(.*?)<\/div>/is',
        'linkCount' => '/videos.*?<span class="counter_value">(.*?)<\/span> <span class="counter_type">links<\/span>/is',
        'videoCount' => '/photos.*?<span class="counter_value">(.*?)<\/span> <span class="counter_type">videos<\/span>/is',
        'photoCount' => '/members.*?<span class="counter_value">(.*?)<\/span> <span class="counter_type">photos<\/span>/is',
        'description' => '/  <meta name="twitter:description" content="(.*?)"/is',
        'bubbles' => '/(<div class="tgme_widget_message_wrap js-widget_message_wrap.*?">.*?datetime.*?<\/div>)/ms',
        'image' => '/<meta property="og:image" content="(.*?)">/is',
    ];
    private array $messaeg_patterns = [
        'id' => '/class="tgme_widget_message_date".*?href="https:\/\/t\.me\/.*?\/([0-9]+)/ism',
        'date' => '/<time datetime="(.*?)">[0-9:]+<\/time>/is',
        'views' => '/class="tgme_widget_message_views">(.*?)<\/span>/ms',
        'text' => '/class="tgme_widget_message_text js-message_text".*?>(.*?)<\/div>/is',
    ];

    public function __construct($httpClient = null)
    {
        if (!$httpClient) {
            $httpClient = new Client();
        }
        $this->client = $httpClient;
        $this->normalizer = new Normalizer();
    }

    public function load($username): self
    {
        $username = $this->normalizer->username($username);
        $this->username = $username;
        $url = sprintf('https://t.me/s/%s', $username);
        $this->rawContent = $this->client->get($url)->getBody()->getContents();
        $this->prepareChannelContent();
        return $this;
    }

    private function prepareChannelContent()
    {
        foreach ($this->channel_info_patterns as $key => $value) {
            preg_match($this->channel_info_patterns[$key], $this->rawContent, $matches);
            $this->info[$key] = $matches[1] ?? '';
        }
    }

    public function __call($method, $params)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$params);
        }
        $key = strtolower(substr($method, 3));

        if (array_key_exists($key, $this->info)) {
            return $this->info[$key];
        }

        return null;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function getDescription(): string
    {
        return str_replace("\n", '', $this->info['description']);
    }

    private function spliteMessages(): array
    {
        preg_match_all($this->channel_info_patterns['bubbles'], $this->rawContent, $matches);
        foreach ($matches[1] as $value) {
            $this->messages[$this->parseMessage($value)->getId()] = $this->parseMessage($value);
        }
        return $this->messages;
    }

    public function eagerLoad($before): static
    {
        $before = $before == null ? null : '?before=' . $before;
        $url = sprintf('https://t.me/s/%s' . $before, $this->username);
        $this->rawContent = $this->client->get($url)->getBody()->getContents();
        return $this;
    }

    public function getMessages(): array
    {
        return $this->spliteMessages();
    }

    public function parseMessage($message): Message
    {
        $result = [];
        $patterns = $this->messaeg_patterns;

        preg_match($patterns['id'], $message, $res);
        $result['id'] = (int)$res[1];

        preg_match($patterns['date'], $message, $res);
        $result['date']['date'] = $res[1];
        $result['date']['unix'] = strtotime($res[1]);

        preg_match($patterns['views'], $message, $res);
        $result['views'] = $res[1] ?? "";

        preg_match($patterns['text'], $message, $res);
        $result['text'] = strip_tags(trim(preg_replace('/\s\s+/', ' ', html_entity_decode($res[1] ?? "", ENT_QUOTES))));

        $dateString = str_replace('T', ' ', mb_substr($result["date"]["date"], 0, 19));
        $dateTime = (DateTime::createFromFormat('Y-m-d H:i:s', $dateString))->setTimezone(new DateTimeZone('Europe/Berlin'));

        return new Message($result['id'], $dateTime, $result['views'], $result['text']);
    }

    public function messageToDatabase(array $listOfMessages = null): void
    {
        $host = "localhost";
        $name = "DEV_TEESAVE";
        $user = "planlosdb";
        $password = "NupUfy70#";

        try {
            $mysql = new PDO("mysql:host=$host;dbname=$name", $user, $password);
        } catch (PDOException $e) {
            echo "SQL Error: " . $e->getMessage();
        }

        /**
         * @var Message $message
         */
        foreach ($listOfMessages as $message) {
            $id = $message->getId();
            $created = ($message->getDateTime())->format('Y-m-d H:i:s');
            $views = $message->getViews();
            $text = $message->getText();

            if ($this->isMessageExisting($id, $mysql)) {
                //TODO Update Message
                continue;
            }

            $stmtAddMessage = $mysql->prepare("INSERT INTO messages (message_id, message_created, message_views, message_text, message_url) VALUES (:message_id, :message_created, :message_views, :message_text, 'https://t.me/freiesachsen');");
            $stmtAddMessage->bindParam(':message_id', $id);
            $stmtAddMessage->bindParam(':message_created', $created);
            $stmtAddMessage->bindParam(':message_views', $views);
            $stmtAddMessage->bindParam(':message_text', $text);
            $stmtAddMessage->execute();
        }
    }

    private function isMessageExisting(int $id, PDO $mysql): bool
    {
        $stmtIsMessageExist = $mysql->prepare("SELECT * FROM messages WHERE message_id = :message_id");
        $stmtIsMessageExist->bindParam(':message_id', $id);
        $stmtIsMessageExist->execute();

        $countIsMessageExist = $stmtIsMessageExist->rowCount();

        if ($countIsMessageExist >= 1) {
            return true;
        }

        return false;
    }

}