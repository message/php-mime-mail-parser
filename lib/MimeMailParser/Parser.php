<?php

namespace MimeMailParser;

use MimeMailParser\Exception\RuntimeException;

/**
 * Fast Mime Mail parser Class using PHP's MailParse Extension
 * 
 * @author gabe@fijiwebdesign.com
 * @url http://www.fijiwebdesign.com/
 * @license http://creativecommons.org/licenses/by-sa/3.0/us/
 * @version $Id$
 */
class Parser
{

	/**
	 * PHP MimeParser Resource ID
	 */
	public $resource;

	/**
	 * A file pointer to email
	 */
	public $stream;

	/**
	 * A text of an email
	 */
	public $data;

	/**
	 * Parts array
	 */
	public $parts;

	/**
	 * Stream Resources for Attachments
	 */
	public $attachment_streams;

	/**
	 * Inialize some stuff
	 * @return 
	 */
	public function __construct()
	{
		$this->attachment_streams = array();
	}

	/**
	 * Free the held resouces
	 * @return void
	 */
	public function __destruct()
	{
		// clear the email file resource
		if (is_resource($this->stream)) {
			fclose($this->stream);
		}
		// clear the MailParse resource
		if (is_resource($this->resource)) {
			mailparse_msg_free($this->resource);
		}
		// remove attachment resources
		foreach ($this->attachment_streams as $stream) {
			fclose($stream);
		}
	}

	/**
	 * Set the file path we use to get the email text
	 * @return Object MimeMailParser Instance
	 * @param $path Object
	 */
	public function setPath($path)
	{
		// should parse message incrementally from file
		$this->resource = mailparse_msg_parse_file($path);
		$this->stream = fopen($path, 'r');
		$this->parse();
		return $this;
	}

	/**
	 * Set the Stream resource we use to get the email text
	 * @return Object MimeMailParser Instance
	 * @param $stream Resource
	 */
	public function setStream($stream)
	{

		// streams have to be cached to file first
		if (get_resource_type($stream) == 'stream') {
			$tmp_fp = tmpfile();
			if ($tmp_fp) {
				while ( ! feof($stream)) {
					fwrite($tmp_fp, fread($stream, 2028));
				}
				fseek($tmp_fp, 0);
				$this->stream = & $tmp_fp;
			} else {
				throw new RuntimeException('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
			}
			fclose($stream);
		} else {
			$this->stream = $stream;
		}

		$this->resource = mailparse_msg_create();
		// parses the message incrementally low memory usage but slower
		while ( ! feof($this->stream)) {
			mailparse_msg_parse($this->resource, fread($this->stream, 2082));
		}
		$this->parse();
		return $this;
	}

	/**
	 * Set the email text
	 * @return Object MimeMailParser Instance 
	 * @param $data String
	 */
	public function setText($data)
	{
		$this->resource = mailparse_msg_create();
		// does not parse incrementally, fast memory hog might explode
		mailparse_msg_parse($this->resource, $data);
		$this->data = $data;
		$this->parse();
		return $this;
	}

	/**
	 * Parse the Message into parts
	 * @return void
	 * @private
	 */
	private function parse()
	{
		$structure = mailparse_msg_get_structure($this->resource);
		$this->parts = array();
		foreach ($structure as $part_id) {
			$part = mailparse_msg_get_part($this->resource, $part_id);
			$this->parts[$part_id] = mailparse_msg_get_part_data($part);
		}
	}

	/**
	 * Retrieve the Email Headers
	 * @return Array
	 */
	public function getHeaders()
	{
		if (isset($this->parts[1])) {
			return $this->getPartHeaders($this->parts[1]);
		} else {
			throw new RuntimeException('Parser::setPath() or Parser::setText() must be called before retrieving email headers.');
		}
		return false;
	}

	/**
	 * Retrieve the raw Email Headers
	 * @return string
	 */
	public function getHeadersRaw()
	{
		if (isset($this->parts[1])) {
			return $this->getPartHeaderRaw($this->parts[1]);
		} else {
			throw new RuntimeException('Parser::setPath() or Parser::setText() must be called before retrieving email headers.');
		}
		return false;
	}

	/**
	 * Retrieve a specific Email Header
	 * @return String
	 * @param $name String Header name
	 */
	public function getHeader($name)
	{
		if (isset($this->parts[1])) {
			$headers = $this->getPartHeaders($this->parts[1]);
			if (isset($headers[$name])) {
				return $headers[$name];
			}
		} else {
			throw new RuntimeException('Parser::setPath() or Parser::setText() must be called before retrieving email headers.');
		}
		return false;
	}

	/**
	 * Returns the email message body in the specified format
	 * @return Mixed String Body or False if not found
	 * @param $type Object[optional]
	 */
	public function getMessageBody($type = 'text')
	{
		$body = false;
		$mime_types = array(
			'text' => 'text/plain',
			'html' => 'text/html'
		);
		if (in_array($type, array_keys($mime_types))) {
			foreach ($this->parts as $part) {
				if ($this->getPartContentType($part) == $mime_types[$type] && !$this->getPartContentDisposition($part)) {
					$headers = $this->getPartHeaders($part);
					$body = $this->decode($this->getPartBody($part), array_key_exists('content-transfer-encoding', $headers) ? $headers['content-transfer-encoding'] : '');
				}
			}
		} else {
			throw new RuntimeException('Invalid type specified for Parser::getMessageBody. "type" can either be text or html.');
		}
		return $body;
	}

	/**
	 * get the headers for the message body part.
	 * @return Array
	 * @param $type Object[optional]
	 */
	public function getMessageBodyHeaders($type = 'text')
	{
		$headers = false;
		$mime_types = array(
			'text' => 'text/plain',
			'html' => 'text/html'
		);
		if (in_array($type, array_keys($mime_types))) {
			foreach ($this->parts as $part) {
				if ($this->getPartContentType($part) == $mime_types[$type]) {
					$headers = $this->getPartHeaders($part);
				}
			}
		} else {
			throw new RuntimeException('Invalid type specified for Parser::getMessageBody. "type" can either be text or html.');
		}
		return $headers;
	}

	/**
	 * Returns the attachments contents in order of appearance
	 * @return Array
	 * @param $type Object[optional]
	 */
	public function getAttachments()
	{
		$attachments = array();
		$dispositions = array("attachment", "inline");
		foreach ($this->parts as $part) {
			$disposition = $this->getPartContentDisposition($part);
			if (in_array($disposition, $dispositions) || isset($part['content-id']) ) {
				$attachments[] = new Attachment(
						!empty($part['disposition-filename']) ? $part['disposition-filename'] : $part['content-name'],
						$this->getPartContentType($part),
						$this->getAttachmentStream($part),
						$disposition,
						$this->getPartHeaders($part)
				);
			}
		}
		return $attachments;
	}

	/**
	 * Return the Headers for a MIME part
	 * @return Array
	 * @param $part Array
	 */
	protected function getPartHeaders($part)
	{
		if (isset($part['headers'])) {
			return $part['headers'];
		}
		return false;
	}

	/**
	 * Return the ContentType of the MIME part
	 * @return String
	 * @param $part Array
	 */
	protected function getPartContentType($part)
	{
		if (isset($part['content-type'])) {
			return $part['content-type'];
		}
		return false;
	}

	/**
	 * Return the Content Disposition
	 * @return String
	 * @param $part Array
	 */
	protected function getPartContentDisposition($part)
	{
		if (isset($part['content-disposition'])) {
			return $part['content-disposition'];
		}
		return false;
	}

	/**
	 * Retrieve the raw Header of a MIME part
	 * @return String
	 * @param $part Object
	 */
	protected function getPartHeaderRaw(&$part)
	{
		$header = '';
		if ($this->stream) {
			$header = $this->getPartHeaderFromFile($part);
		} else if ($this->data) {
			$header = $this->getPartHeaderFromText($part);
		} else {
			throw new RuntimeException('Parser::setPath() or Parser::setText() must be called before retrieving email parts.');
		}
		return $header;
	}

	/**
	 * Retrieve the Body of a MIME part
	 * @return String
	 * @param $part Object
	 */
	protected function getPartBody(&$part)
	{
		$body = '';
		if ($this->stream) {
			$body = $this->getPartBodyFromFile($part);
		} else if ($this->data) {
			$body = $this->getPartBodyFromText($part);
		} else {
			throw new RuntimeException('Parser::setPath() or Parser::setText() must be called before retrieving email parts.');
		}
		return $body;
	}

	/**
	 * Retrieve the Header from a MIME part from file
	 * @return String Mime Header Part
	 * @param $part Array
	 */
	protected function getPartHeaderFromFile(&$part)
	{
		$start = $part['starting-pos'];
		$end = $part['starting-pos-body'];
		fseek($this->stream, $start, SEEK_SET);
		$header = fread($this->stream, $end - $start);
		return $header;
	}

	/**
	 * Retrieve the Body from a MIME part from file
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	protected function getPartBodyFromFile(&$part)
	{
		$start = $part['starting-pos-body'];
		$end = $part['ending-pos-body'];
		fseek($this->stream, $start, SEEK_SET);
		$body = fread($this->stream, $end - $start);
		return $body;
	}

	/**
	 * Retrieve the Header from a MIME part from text
	 * @return String Mime Header Part
	 * @param $part Array
	 */
	protected function getPartHeaderFromText(&$part)
	{
		$start = $part['starting-pos'];
		$end = $part['starting-pos-body'];
		$header = substr($this->data, $start, $end - $start);
		return $header;
	}

	/**
	 * Retrieve the Body from a MIME part from text
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	protected function getPartBodyFromText(&$part)
	{
		$start = $part['starting-pos-body'];
		$end = $part['ending-pos-body'];
		$body = substr($this->data, $start, $end - $start);
		return $body;
	}

	/**
	 * Read the attachment Body and save temporary file resource
	 * @return String Mime Body Part
	 * @param $part Array
	 */
	protected function getAttachmentStream(&$part)
	{
		$temp_fp = tmpfile();

		array_key_exists('content-transfer-encoding', $part['headers']) ? $encoding = $part['headers']['content-transfer-encoding'] : $encoding = '';

		if ($temp_fp) {
			if ($this->stream) {
				$start = $part['starting-pos-body'];
				$end = $part['ending-pos-body'];
				fseek($this->stream, $start, SEEK_SET);
				$len = $end - $start;
				$written = 0;
				$write = 2028;
				while ($written < $len) {
                    if (($written + $write < $len)) {
                        $write = $len - $written;
                    } else if ($len < $write) {
                        $write = $len;
                    }
					$part = fread($this->stream, $write);
					fwrite($temp_fp, $this->decode($part, $encoding));
					$written += $write;
				}
			} else if ($this->data) {
				$attachment = $this->decode($this->getPartBodyFromText($part), $encoding);
				fwrite($temp_fp, $attachment, strlen($attachment));
			}
			fseek($temp_fp, 0, SEEK_SET);
		} else {
			throw new RuntimeException('Could not create temporary files for attachments. Your tmp directory may be unwritable by PHP.');
		}
		return $temp_fp;
	}

	/**
	 * Decode the string depending on encoding type.
	 * @return String the decoded string.
	 * @param $encodedString    The string in its original encoded state.
	 * @param $encodingType     The encoding type from the Content-Transfer-Encoding header of the part.
	 */
	private function decode($encodedString, $encodingType)
	{
		if (strtolower($encodingType) == 'base64') {
			return base64_decode($encodedString);
		} else if (strtolower($encodingType) == 'quoted-printable') {
			return quoted_printable_decode($encodedString);
		} else {
			return $encodedString;
		}
	}

}
