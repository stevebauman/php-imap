<h1 align="center">PHP IMAP</h1>
    
<p align="center">Integrate IMAP into your PHP application. A fork of the <a href="https://github.com/Webklex/php-imap" target="_blank">webklex/php-imap</a> library.</p>

<p align="center">
<a href="https://github.com/stevebauman/php-imap/actions"><img src="https://img.shields.io/github/actions/workflow/status/stevebauman/php-imap/run-tests.yml?branch=master&style=flat-square"></a>
<a href="https://packagist.org/packages/stevebauman/php-imap"><img src="https://img.shields.io/packagist/dt/stevebauman/php-imap.svg?style=flat-square"></a>
<a href="https://packagist.org/packages/stevebauman/php-imap"><img src="https://img.shields.io/packagist/v/stevebauman/php-imap.svg?style=flat-square"></a>
<a href="https://packagist.org/packages/stevebauman/php-imap"><img src="https://img.shields.io/packagist/l/stevebauman/php-imap.svg?style=flat-square"></a>
</p>

## Description

PHP IMAP is a library that helps you interact with mailboxes over IMAP.

The `imap` extension is not required to use this library. The protocol has been completely implemented in PHP.

Support for IDLE is also included, which provides the ability to await new messages (and act upon them) indefinitely.

## Requirements

PHP >= 8.1

## Documentation

Original Documentation: [php-imap.com](https://www.php-imap.com/)

## Usage

### Connecting

```php
use Webklex\PHPIMAP\ClientManager;

$manager = new ClientManager([
    'options' => [
        'debug' => true,
    ],
    'accounts' => [
        'default' => [
            'port' => 993,
            'host' => 'imap.example.com',
            'username' => 'user@example.com',
            'password' => 'secret',
            'encryption' => 'tls',
        ],
    ],
])

/** @var \Webklex\PHPIMAP\Client $client */
$client = $manager->account('default');

// Connect to the IMAP Server.
$client->connect();
```

### Fetching Messages

To fetch messages from a folder, you may use the `messages` method:

```php
/** @var \Webklex\PHPIMAP\Folder $folder */
$inbox = $client->getFolder('INBOX');

/** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
$messages = $folder->messages()->all()->get();

/** @var \Webklex\PHPIMAP\Message $message */
foreach($messages as $message) {
    echo $message->getSubject().'<br />';
    
    echo 'Attachments: '.$message->getAttachments()->count().'<br />';
    
    echo $message->getHTMLBody();
    
    // Move the current Message to 'INBOX.read'.
    if ($message->move('INBOX.read') == true) {
        echo 'Message has been moved';
    } else {
        echo 'Message could not be moved';
    }
}
```

### Awaiting New Messages (Idle)

To await new messages, you may use the `idle` method:

> This method will listen for new messages indefinitely.

```php
use Webklex\PHPIMAP\Message;

$client->getFolder('INBOX')->idle(function (Message $message) {
    // Do something with the new message.
}, timeout: 60); // in seconds
```

## Tests

### Quick-Test / Static Test

To disable all test which require a live mailbox, please copy the `phpunit.xml.dist` to `phpunit.xml` and adjust the configuration:
```xml
<php>
    <env name="LIVE_MAILBOX" value="false"/>
</php>
```

### Full-Test / Live Mailbox Test

To run all tests, you need to provide a valid imap configuration.

To provide a valid imap configuration, please copy the `phpunit.xml.dist` to `phpunit.xml` and adjust the configuration:
```xml
<php>
    <env name="LIVE_MAILBOX" value="true"/>
    <env name="LIVE_MAILBOX_DEBUG" value="true"/>
    <env name="LIVE_MAILBOX_HOST" value="mail.example.local"/>
    <env name="LIVE_MAILBOX_PORT" value="993"/>
    <env name="LIVE_MAILBOX_VALIDATE_CERT" value="false"/>
    <env name="LIVE_MAILBOX_QUOTA_SUPPORT" value="true"/>
    <env name="LIVE_MAILBOX_ENCRYPTION" value="ssl"/>
    <env name="LIVE_MAILBOX_USERNAME" value="root@example.local"/>
    <env name="LIVE_MAILBOX_PASSWORD" value="foobar"/>
</php>
```

The test account should **not** contain any important data, as it will be deleted during the test.
Furthermore, the test account should be able to create new folders, move messages and should **not** be used by any other
application during the test.

It's recommended to use a dedicated test account for this purpose. You can use the provided `Dockerfile` to create an imap server used for testing purposes.

Build the docker image:
```bash
cd .github/docker

docker build -t php-imap-server .
```
Run the docker image:
```bash
docker run --name imap-server -p 993:993 --rm -d php-imap-server
```
Stop the docker image:
```bash
docker stop imap-server
```

## Known issues

| Error                                                                      | Solution                                                                                |
|:---------------------------------------------------------------------------|:----------------------------------------------------------------------------------------|
| Kerberos error: No credentials cache file found (try running kinit) (...)  | Uncomment "DISABLE_AUTHENTICATOR" inside your config and use the `legacy-imap` protocol |
