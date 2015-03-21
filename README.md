php-mime-mail-parser
====================

# THIS REPOSITORY IS DEPRECATED. MOVED TO https://github.com/php-mime-mail-parser/php-mime-mail-parser

PHP 5.3+ Fork of  http://code.google.com/p/php-mime-mail-parser

## Contributions
Feel free to contribute.

## Composer
```json
"require": {
	"messaged/php-mime-mail-parser": "v1.0.7"
}
```

## Usage example

```php
use MimeMailParser\Parser;
use MimeMailParser\Attachment;

$parser = new Parser();
$parser->setText(file_get_contents('/path/to/mail'));

$to = $parser->getHeader('to');
$delivered_to = $parser->getHeader('delivered-to');
$from = $parser->getHeader('from');
$subject = $parser->getHeader('subject');
$text = $parser->getMessageBody('text');
$html = $parser->getMessageBody('html');
$attachments = $parser->getAttachments();

// Write attachments to disk
foreach ($attachments as $attachment) {
    $attachment->saveAttachment('/tmp');
}
```
