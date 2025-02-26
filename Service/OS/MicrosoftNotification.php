<?php

namespace RMS\PushNotificationsBundle\Service\OS;

use Psr\Log\LoggerInterface;
use RMS\PushNotificationsBundle\Exception\InvalidMessageTypeException;
use RMS\PushNotificationsBundle\Message\WindowsphoneMessage;
use RMS\PushNotificationsBundle\Message\MessageInterface;
use Buzz\Browser,
    Buzz\Client\Curl;

class MicrosoftNotification implements OSNotificationServiceInterface
{
    /**
     * Browser object
     *
     * @var \Buzz\Browser
     */
    protected $browser;

    /**
     * Monolog logger
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param $timeout
     * @param $logger
     */
    public function __construct($timeout, $logger)
    {
        $this->browser = new Browser(new Curl());
        $this->browser->getClient()->setVerifyPeer(false);
        $this->browser->getClient()->setTimeout($timeout);
        $this->logger = $logger;
    }

    public function send(MessageInterface $message)
    {
        if (!$message instanceof WindowsphoneMessage) {
            throw new InvalidMessageTypeException(sprintf("Message type '%s' not supported by MPNS", get_class($message)));
        }

        $headers = array(
            'Content-Type: text/xml',
            'X-WindowsPhone-Target: ' . $message->getTarget(),
            'X-NotificationClass: ' . $message->getNotificationClass()
        );

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><wp:Notification xmlns:wp="WPNotification" />');

        $msgBody = $message->getMessageBody();

        if ($message->getTarget() == WindowsphoneMessage::TYPE_TOAST) {
            $toast = $xml->addChild('wp:Toast');
            $toast->addChild('wp:Text1', htmlspecialchars($msgBody['text1'], ENT_XML1|ENT_QUOTES));
            $toast->addChild('wp:Text2', htmlspecialchars($msgBody['text2'], ENT_XML1|ENT_QUOTES));
        }

        $response = $this->browser->post($message->getDeviceIdentifier(), $headers, $xml->asXML());

        if (!$response->isSuccessful()) {
            $this->logger->error($response->getStatusCode(). ' : '. $response->getReasonPhrase());
        }

        return $response->isSuccessful();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'rms_push_notifications.os.windowsphone';
    }
}
