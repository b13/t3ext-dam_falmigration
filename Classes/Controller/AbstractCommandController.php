<?php
namespace B13\DamFalmigration\Controller;
/**
 *  Copyright notice
 *
 *  ⓒ 2014 Michiel Roos <michiel@maxserv.nl>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is free
 *  software; you can redistribute it and/or modify it under the terms of the
 *  GNU General Public License as published by the Free Software Foundation;
 *  either version 2 of the License, or (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful, but
 *  WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 *  or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 *  more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 */
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\DamFalmigration\Service;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

// I can haz color / use unicode?
if (DIRECTORY_SEPARATOR !== '\\') {
	define('USE_COLOR', function_exists('posix_isatty') && posix_isatty(STDOUT));
	define('UNICODE', TRUE);
} else {
	define('USE_COLOR', getenv('ANSICON') !== FALSE);
	define('UNICODE', FALSE);
}

// Get terminal width
if (@exec('tput cols')) {
	define('TERMINAL_WIDTH', exec('tput cols'));
} else {
	define('TERMINAL_WIDTH', 79);
}

/**
 * Abstract Command Controller
 *
 * @package B13\DamFalmigration
 * @subpackage Controller
 */
class AbstractCommandController extends CommandController {

	/**
	 * Output FlashMessage
	 *
	 * @param FlashMessage $message
	 *
	 * @return void
	 */
	public function outputMessage($message = NULL) {
		if ($message->getTitle()) {
			$this->outputLine($message->getTitle());
		}
		if ($message->getMessage()) {
			$this->outputLine($message->getMessage());
		}
		if ($message->getSeverity() !== FlashMessage::OK) {
			$this->sendAndExit(1);
		}
	}

	/**
	 * Normal message
	 *
	 * @param $message
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function message($message = NULL, $flushOutput = TRUE) {
		$this->outputLine($message);
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Informational message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function infoMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '★ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;36m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Error message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function errorMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '✖ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[31m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Warning message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function warningMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon) {
			$icon = '! ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;33m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Success message
	 *
	 * @param string $message
	 * @param boolean $showIcon
	 * @param boolean $flushOutput
	 *
	 * @return void
	 */
	public function successMessage($message = NULL, $showIcon = FALSE, $flushOutput = TRUE) {
		$icon = '';
		if ($showIcon && UNICODE) {
			$icon = '✔ ';
		}
		if (USE_COLOR) {
			$this->outputLine("\033[0;32m" . $icon . $message . "\033[0m");
		} else {
			$this->outputLine($icon . $message);
		}
		if ($flushOutput) {
			$this->response->send();
		}
	}

	/**
	 * Show a header message
	 *
	 * @param $message
	 * @param string $style
	 *
	 * @return void
	 */
	public function headerMessage($message, $style = 'info') {
		// Crop the message
		$message = substr($message, 0, TERMINAL_WIDTH - 3);
		if (UNICODE) {
			$linePaddingLength = mb_strlen('─') * (TERMINAL_WIDTH - 2);
			$message =
				'┌' . str_pad('', $linePaddingLength, '─') . '┐' . LF .
				'│ ' . str_pad($message, TERMINAL_WIDTH - 3) . '│' . LF .
				'└' . str_pad('', $linePaddingLength, '─') . '┘';
		} else {
			$message =
				str_pad('', TERMINAL_WIDTH, '-') . LF .
				'+ ' . str_pad($message, TERMINAL_WIDTH - 3) . '+' . LF .
				str_pad('', TERMINAL_WIDTH, '-');
		}
		switch ($style) {
			case 'error':
				$this->errorMessage($message, FALSE);
				break;
			case 'info':
				$this->infoMessage($message, FALSE);
				break;
			case 'success':
				$this->successMessage($message, FALSE);
				break;
			case 'warning':
				$this->warningMessage($message, FALSE);
				break;
			default:
				$this->message($message);
		}
	}
}
