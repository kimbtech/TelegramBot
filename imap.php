<?php

class IMAP {

	private $imap_stream;

	public function __construct( $mail ){
		$this->imap_stream = imap_open(
			$mail['server'],
			$mail['user'],
			$mail['pw']
		) or die('cannot connect');
	}

	private function decodeEncodedBody( $enc, $data ){
		switch ($enc) {
			case ENC7BIT:
				return imap_qprint($data);
			case ENC8BIT:
				return quoted_printable_decode(imap_8bit($data));
			case ENCBASE64:
				return imap_base64($data);
			case ENCQUOTEDPRINTABLE:
				return quoted_printable_decode($data);
			default:
				return $data;
		}	
	}
  
	private function bodyEncodingHeader( $enc ){
		switch ($enc) {
			case ENC7BIT:
				return '7bit';
			case ENC8BIT:
				return '8bit';
			case ENCBASE64:
				return 'base64';
			case ENCQUOTEDPRINTABLE:
				return 'quoted-printable';
			default:
				return '';
		}	
	}

	private function partAnalyse( $mail, $parts, $prefix = '' ){
		foreach( $parts as $key => $part ){
			if( $part->type == TYPETEXT && !$part->ifdisposition ){
				$simplecontent = $this->decodeEncodedBody( $part->encoding, imap_fetchbody( $this->imap_stream, $mail->msgno, $prefix . ($key + 1) ) );
				if( $part->subtype == 'PLAIN' ){
					$simplecontentPLAIN = $simplecontent;
				}
			}
			if( isset( $part->parts ) ){
				$simplecontent = $this->partAnalyse( $mail, $part->parts, $prefix . ($key + 1) . '.' );
			}
		}
		return empty( $simplecontentPLAIN ) ? $simplecontent : $simplecontentPLAIN;
	}

	public function getNew(){
		$about = imap_check( $this->imap_stream );
		if( $about->Nmsgs < 1 ){
			return array(); //empty
		}

		$mails = imap_fetch_overview( $this->imap_stream, '1:'.$about->Nmsgs);

		$return = array();
		foreach ($mails as $key => $mail) {
			$body = imap_body( $this->imap_stream , $mail->msgno );
			$simplecontent = $body;

			$header = imap_fetchheader( $this->imap_stream, $mail->msgno );
			
			$tos = array();
			preg_match_all( '/(?:[^-]To|CC|BCC|Envelope-To):.*(?<=[:< ])([A-Za-z0-9\+\-\_\.]+)(?=\@'. str_replace( '.', '\.', CONFIG::$SYSDOMAIN ) .')/i', $header, $tos );
			$tos = array_map( 'strtolower', $tos[1] );

			$mime = stripos( $header, 'MIME-Version: 1.0' ) !== false;

			if( $mime ){		
				$mtype = array('text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'model', 'other');
				$structure = imap_fetchstructure($this->imap_stream , $mail->msgno );

				$mimetype = $mtype[$structure->type] . '/' . strtolower( $structure->subtype );
				if($structure->type == TYPEMULTIPART) {
					foreach( $structure->parameters as $p ){
						$mimetype .= "; ". $p->attribute . '="' . $p->value . '"';
					}
				}
				if( isset($structure->parts) ){
					$simplecontent = $this->partAnalyse( $mail, $structure->parts );
				}
				else {
					$simplecontent = $this->decodeEncodedBody( $structure->encoding, $body);
					$encoding = $this->bodyEncodingHeader( $structure->encoding );
				}
			}

			$return[] = array(
				'content' => $body,
				'subject' => isset($mail->subject) ? $mail->subject : '',
				'from' => isset($mail->from) ? $mail->from : '',
				'to' => array_unique( $tos ),
				'mime' => $mime,
				'ct' => $mime ? $mimetype : 'text/plain',
				'simplecontent' => mb_substr( $simplecontent, 0, 3500),
				'encoding' => empty( $encoding ) ? '' : $encoding
			);

			if( CONFIG::$DELETMAILS ){
				imap_delete( $this->imap_stream, $mail->msgno );
			}
		}
		return $return;
	}

	public function __destruct(){
		imap_expunge( $this->imap_stream );
		imap_close( $this->imap_stream );
	}
}

?>
