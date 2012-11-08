php-mime-mail-parser
====================

PHP 5.3+ Fork of  http://code.google.com/p/php-mime-mail-parser


## Composer 
```json
"require": {
	"messaged/php-mime-mail-parser": "dev-master"
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
```