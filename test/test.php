<?php


function _resetErrors() {
	if (function_exists('error_clear_last')) {
		error_clear_last();
	}
	else {
		// set error_get_last() to defined state by forcing an undefined variable error
		set_error_handler('_resetErrorsHandler', 0);
		@$undefinedVariable;
		restore_error_handler();
	}
}

function _resetErrorsHandler($errno, $errstr, $errfile, $errline) {
	//Do nothing
}

// This is a slightly modified version of the original test function.
// See wordfence/vendor/wordfence/wf-waf/src/lib/rules.php:897
function fileHasPHP( $filename ) {
	do {
		if ( ! is_file( $filename ) ) {
			throw new Exception( 'expected file to exist' );
		}
		$fh = @fopen( $filename, 'r' );
		if ( ! $fh ) {
			throw new Exception( 'expected file to exist' );
		}

		$totalRead                = 0;
		$insideOpenTag            = false;
		$hasExecutablePHP         = false;
		$possiblyHasExecutablePHP = false;
		$hasOpenParen             = false;
		$hasCloseParen            = false;
		$backtickCount            = 0;
		$wrappedTokenCheckBytes   = '';
		$maxTokenSize             = 15; // __halt_compiler
		$possibleWrappedTokens    = array( '<?php', '<?=', '<?', '?>', 'exit', 'new', 'clone', 'echo', 'print', 'require', 'include', 'require_once', 'include_once', '__halt_compiler' );

		$readsize = 100 * 1024; // 100k at a time
		while ( ! feof( $fh ) ) {
			$data           = fread( $fh, $readsize );
			$actualReadsize = strlen( $data );
			$totalRead     += $actualReadsize;
			if ( $totalRead < 1 ) {
				break;
			}

			// Make sure we didn't miss PHP split over a chunking boundary
			$wrappedCheckLength = strlen( $wrappedTokenCheckBytes );
			if ( $wrappedCheckLength > 0 ) {
				$testBytes = $wrappedTokenCheckBytes . substr( $data, 0, min( $maxTokenSize, $actualReadsize ) );
				foreach ( $possibleWrappedTokens as $t ) {
					$position = strpos( $testBytes, $t );
					if ( $position !== false && $position < $wrappedCheckLength && $position + strlen( $t ) >= $wrappedCheckLength ) { // Found a token that starts before this segment of data and ends within it
						$data = substr( $wrappedTokenCheckBytes, $position ) . $data;
						break;
					}
				}
			}

			// Make sure it tokenizes correctly if chunked
			if ( $insideOpenTag ) {
				if ( $possiblyHasExecutablePHP ) {
					$data = '<?= ' . $data;
				} else {
					$data = '<?php ' . $data;
				}
			}

			// Tokenize the data and check for PHP
			_resetErrors();
			$tokens      = @token_get_all( $data );
			$error       = error_get_last();

			if ( $error !== null && stripos( $error['message'], 'Unexpected character in input' ) !== false ) {
				break;
			}

			if ( $error !== null && feof( $fh ) && stripos( $error['message'], 'Unterminated comment' ) !== false ) {
				break;
			}

			$offset = 0;
			foreach ( $tokens as $token ) {
				if ( is_array( $token ) ) {
					$offset += strlen( $token[1] );
					switch ( $token[0] ) {
						case T_OPEN_TAG:
							$insideOpenTag            = true;
							$hasOpenParen             = false;
							$hasCloseParen            = false;
							$backtickCount            = 0;
							$possiblyHasExecutablePHP = false;

							if ( $error !== null && stripos( $error['message'], 'Unterminated comment' ) !== false ) {
								$testOffset   = $offset - strlen( $token[1] );
								$commentStart = strpos( $data, '/*', $testOffset );
								if ( $commentStart !== false ) {
									$testBytes   = substr( $data, $testOffset, $commentStart - $testOffset );
									_resetErrors();
									@token_get_all( $testBytes );
									$error = error_get_last();
									if ( $error !== null && stripos( $error['message'], 'Unexpected character in input' ) !== false ) {
										break 3;
									}
								}
							}

							break;

						case T_OPEN_TAG_WITH_ECHO:
							$insideOpenTag            = true;
							$hasOpenParen             = false;
							$hasCloseParen            = false;
							$backtickCount            = 0;
							$possiblyHasExecutablePHP = true;

							if ( $error !== null && stripos( $error['message'], 'Unterminated comment' ) !== false ) {
								$testOffset   = $offset - strlen( $token[1] );
								$commentStart = strpos( $data, '/*', $testOffset );
								if ( $commentStart !== false ) {
									$testBytes = substr( $data, $testOffset, $commentStart - $testOffset );
									_resetErrors();
									@token_get_all( $testBytes );
									$error = error_get_last();
									if ( $error !== null && stripos( $error['message'], 'Unexpected character in input' ) !== false ) {
										break 3;
									}
								}
							}

							break;

						case T_CLOSE_TAG:
							$insideOpenTag = false;
							if ( $possiblyHasExecutablePHP ) {
								$hasExecutablePHP = true; // Assume the echo short tag outputted something useful
							}
							break 2;

						case T_NEW:
						case T_CLONE:
						case T_ECHO:
						case T_PRINT:
						case T_REQUIRE:
						case T_INCLUDE:
						case T_REQUIRE_ONCE:
						case T_INCLUDE_ONCE:
						case T_HALT_COMPILER:
						case T_EXIT:
							$hasExecutablePHP = true;
							break 2;
					}
				} else {
					$offset += strlen( $token );
					switch ( $token ) {
						case '(':
							$hasOpenParen = true;
							break;
						case ')':
							$hasCloseParen = true;
							break;
						case '`':
							$backtickCount++;
							break;
					}
				}
				if ( ! $hasExecutablePHP && ( ( $hasOpenParen && $hasCloseParen ) || ( $backtickCount > 1 && $backtickCount % 2 === 0 ) ) ) {
					$hasExecutablePHP = true;
					break;
				}
			}

			if ( $hasExecutablePHP ) {
				fclose( $fh );
				return true;
			}

			$wrappedTokenCheckBytes = substr( $data, - min( $maxTokenSize, $actualReadsize ) );
		}

		fclose( $fh );
	} while(false);
	return false;
}

function fileHasPHP_v7_0_1($filename) {
	do {
		$fh = @fopen($filename, 'r');
		if (!$fh) {
			throw new Exception( 'expected file to exist' );
		}
		
		$totalRead = 0;
		$insideOpenTag = false;
		$hasExecutablePHP = false;
		$possiblyHasExecutablePHP = false;
		$hasOpenParen = false;
		$hasCloseParen = false;
		$backtickCount = 0;
		$wrappedTokenCheckBytes = '';
		$maxTokenSize = 15; //__halt_compiler
		$possibleWrappedTokens = array('<?php', '<?=', '<?', '?>', 'exit', 'new', 'clone', 'echo', 'print', 'require', 'include', 'require_once', 'include_once', '__halt_compiler');
		
		$readsize = 100 * 1024; //100k at a time
		while (!feof($fh)) {
			$data = fread($fh, $readsize);
			$actualReadsize = strlen($data);
			$totalRead += $actualReadsize;
			if ($totalRead < 1) {
				break;
			}
			
			//Make sure we didn't miss PHP split over a chunking boundary
			$wrappedCheckLength = strlen($wrappedTokenCheckBytes);
			if ($wrappedCheckLength > 0) {
				$testBytes = $wrappedTokenCheckBytes . substr($data, 0, min($maxTokenSize, $actualReadsize));
				foreach ($possibleWrappedTokens as $t) {
					$position = strpos($testBytes, $t);
					if ($position !== false && $position < $wrappedCheckLength && $position + strlen($t) >= $wrappedCheckLength) { //Found a token that starts before this segment of data and ends within it
						$data = substr($wrappedTokenCheckBytes, $position) . $data;
						break;
					}
				}
			}
			
			//Make sure it tokenizes correctly if chunked
			if ($insideOpenTag) {
				if ($possiblyHasExecutablePHP) {
					$data = '<?= ' . $data; 
				}
				else {
					$data = '<?php ' . $data;
				}
			}
			
			//Tokenize the data and check for PHP
			_resetErrors();
			$tokens = @token_get_all($data);
			$error = error_get_last();
			if ($error !== null && stripos($error['message'], 'Unexpected character in input') !== false) {
				break;
			}
			
			if ($error !== null && feof($fh) && stripos($error['message'], 'Unterminated comment') !== false) {
				break;
			}
			
			$offset = 0;
			foreach ($tokens as $token) {
				if (is_array($token)) {
					$offset += strlen($token[1]);
					switch ($token[0]) {
						case T_OPEN_TAG:
							$insideOpenTag = true;
							$hasOpenParen = false;
							$hasCloseParen = false;
							$backtickCount = 0;
							$possiblyHasExecutablePHP = false;
							
							if ($error !== null && stripos($error['message'], 'Unterminated comment') !== false) {
								$testOffset = $offset - strlen($token[1]);
								$commentStart = strpos($data, '/*', $testOffset);
								if ($commentStart !== false) {
									$testBytes = substr($data, $testOffset, $commentStart - $testOffset);
									_resetErrors();
									@token_get_all($testBytes);
									$error = error_get_last();
									if ($error !== null && stripos($error['message'], 'Unexpected character in input') !== false) {
										break 3;
									}
								}
							}
							
							break;
						
						case T_OPEN_TAG_WITH_ECHO:
							$insideOpenTag = true;
							$hasOpenParen = false;
							$hasCloseParen = false;
							$backtickCount = 0;
							$possiblyHasExecutablePHP = true;
							
							if ($error !== null && stripos($error['message'], 'Unterminated comment') !== false) {
								$testOffset = $offset - strlen($token[1]);
								$commentStart = strpos($data, '/*', $testOffset);
								if ($commentStart !== false) {
									$testBytes = substr($data, $testOffset, $commentStart - $testOffset);
									_resetErrors();
									@token_get_all($testBytes);
									$error = error_get_last();
									if ($error !== null && stripos($error['message'], 'Unexpected character in input') !== false) {
										break 3;
									}
								}
							}
							
							break;
						
						case T_CLOSE_TAG:
							$insideOpenTag = false;
							if ($possiblyHasExecutablePHP) {
								$hasExecutablePHP = true; //Assume the echo short tag outputted something useful
							}
							break 2;
							
						case T_NEW:
						case T_CLONE:
						case T_ECHO:
						case T_PRINT:
						case T_REQUIRE:
						case T_INCLUDE:
						case T_REQUIRE_ONCE:
						case T_INCLUDE_ONCE:
						case T_HALT_COMPILER:
						case T_EXIT:
							$hasExecutablePHP = true;
							break 2;
					}
				}
				else {
					$offset += strlen($token);
					switch ($token) {
						case '(':
							$hasOpenParen = true;
							break;
						case ')':
							$hasCloseParen = true;
							break;
						case '`':
							$backtickCount++;
							break;
					}
				}
				if (!$hasExecutablePHP && (($hasOpenParen && $hasCloseParen) || ($backtickCount > 1 && $backtickCount % 2 === 0))) {
					$hasExecutablePHP = true;
					break;
				}
			}
			
			if ($hasExecutablePHP) {
				fclose($fh);
				return true;
			}
			
			$wrappedTokenCheckBytes = substr($data, - min($maxTokenSize, $actualReadsize)); 
		}
		
		fclose($fh);
	} while(false);
	return false;
}

// Test a single file, log the results to the `results/` directory, and print some test status
function test( $filename, $expected ) {
	$result = fileHasPHP( $filename );
	$result_v7_0_1 = fileHasPHP_v7_0_1( $filename );
	if ($expected === true) {
		if ($result === true) {
			error_log("ok     - \"$filename\" contains php and this was detected");
		} else {
			error_log("not ok - \"$filename\" contains php and this was not detected");
		}
	} else {
		if ($result === true) {
			error_log("not ok - \"$filename\" does not contain php and but php was detected");
		} else {
			error_log("ok     - \"$filename\" does not contains php and none was detected");
		}
	}
	if ($result !== $result_v7_0_1) {
		error_log("       - \"$filename\" different in v7.0.1");
	}
}

error_log('# PHP version: ' . phpversion());
test( __DIR__ . '/test.php', true );
test( __DIR__ . '/test-file-01.txt', false );
test( __DIR__ . '/test-file-02.jpg', false );
test( __DIR__ . '/test-file-03.php', true );
test( __DIR__ . '/test-file-04.jpg', false );
test( __DIR__ . '/test-file-05.jpg', false );
test( __DIR__ . '/test-file-06.jpg', false );
error_log(''); 
