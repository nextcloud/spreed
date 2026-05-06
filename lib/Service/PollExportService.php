<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Poll;
use OCA\Talk\Model\Vote;
use ZipArchive;

class PollExportService {

	/**
	 * Generate a spreadsheet file (ODS or CSV) from poll data.
	 *
	 * @param Poll $poll The poll to export
	 * @param list<Vote> $votes Detailed votes (empty for hidden polls)
	 * @param 'ods'|'csv' $format The output format
	 * @return string The file content
	 */
	public function exportToSpreadsheet(Poll $poll, array $votes, string $format): string {
		return match ($format) {
			'csv' => $this->generateCsv($poll, $votes),
			default => $this->generateOds($poll, $votes),
		};
	}

	private function generateOds(Poll $poll, array $votes): string {
		$options = json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR);
		$voteData = json_decode($poll->getVotes(), true, 512, JSON_THROW_ON_ERROR);
		$numVoters = $poll->getNumVoters();
		$hasDetails = !empty($votes);
		$statusStr = $poll->getStatus() === Poll::STATUS_CLOSED ? 'Closed' : 'Open';

		$esc = (fn (string $s): string => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'));

		// Build content.xml
		$content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<office:document-content'
			. ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
			. ' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
			. ' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
			. ' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
			. ' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"'
			. ' office:version="1.3">'
			. '<office:automatic-styles>'
			. '<style:style style:name="bold" style:family="table-cell">'
			. '<style:text-properties fo:font-weight="bold"/>'
			. '</style:style>'
			. '</office:automatic-styles>'
			. '<office:body><office:spreadsheet>';

		// Sheet 1: Summary
		$content .= '<table:table table:name="Summary">';

		// Row 1: Question
		$content .= '<table:table-row>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Question</text:p></table:table-cell>'
			. '<table:table-cell office:value-type="string"><text:p>' . $esc($poll->getQuestion()) . '</text:p></table:table-cell>'
			. '</table:table-row>';

		// Row 2: Total voters
		$content .= '<table:table-row>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Total voters</text:p></table:table-cell>'
			. '<table:table-cell office:value-type="float" office:value="' . $numVoters . '"><text:p>' . $numVoters . '</text:p></table:table-cell>'
			. '</table:table-row>';

		// Row 3: Status
		$content .= '<table:table-row>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Status</text:p></table:table-cell>'
			. '<table:table-cell office:value-type="string"><text:p>' . $esc($statusStr) . '</text:p></table:table-cell>'
			. '</table:table-row>';

		// Row 4: empty
		$content .= '<table:table-row><table:table-cell/></table:table-row>';

		// Row 5: Headers
		$content .= '<table:table-row>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Option</text:p></table:table-cell>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Votes</text:p></table:table-cell>'
			. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Percentage</text:p></table:table-cell>'
			. '</table:table-row>';

		// Data rows
		foreach ($options as $index => $option) {
			$count = $voteData[$index] ?? 0;
			$percentage = $numVoters > 0 ? round(($count / $numVoters) * 100, 1) : 0;
			$content .= '<table:table-row>'
				. '<table:table-cell office:value-type="string"><text:p>' . $esc($option) . '</text:p></table:table-cell>'
				. '<table:table-cell office:value-type="float" office:value="' . (string)$count . '"><text:p>' . (string)$count . '</text:p></table:table-cell>'
				. '<table:table-cell office:value-type="float" office:value="' . (string)$percentage . '"><text:p>' . (string)$percentage . '</text:p></table:table-cell>'
				. '</table:table-row>';
		}

		$content .= '</table:table>';

		// Sheet 2: Votes (if details available)
		if ($hasDetails) {
			$content .= '<table:table table:name="Votes">';

			// Header row
			$content .= '<table:table-row>'
				. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Voter</text:p></table:table-cell>'
				. '<table:table-cell table:style-name="bold" office:value-type="string"><text:p>Option</text:p></table:table-cell>'
				. '</table:table-row>';

			foreach ($votes as $vote) {
				$voterName = $vote->getDisplayName() ?? '';
				$optionText = $options[$vote->getOptionId()] ?? '';
				$content .= '<table:table-row>'
					. '<table:table-cell office:value-type="string"><text:p>' . $esc($voterName) . '</text:p></table:table-cell>'
					. '<table:table-cell office:value-type="string"><text:p>' . $esc($optionText) . '</text:p></table:table-cell>'
					. '</table:table-row>';
			}

			$content .= '</table:table>';
		}

		$content .= '</office:spreadsheet></office:body></office:document-content>';

		// Manifest
		$manifest = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'
			. '<manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.spreadsheet" manifest:full-path="/"/>'
			. '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>'
			. '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>'
			. '<manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>'
			. '</manifest:manifest>';

		// Styles (minimal)
		$stylesXml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<office:document-styles'
			. ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
			. ' office:version="1.3">'
			. '</office:document-styles>';

		// Meta
		$meta = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
			. '<office:document-meta'
			. ' xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
			. ' xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0"'
			. ' office:version="1.3">'
			. '<office:meta>'
			. '<meta:generator>Nextcloud Talk</meta:generator>'
			. '</office:meta>'
			. '</office:document-meta>';

		// Create ZIP
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_export_');
		$zip = new ZipArchive();
		$zip->open($tempFile, ZipArchive::OVERWRITE);

		// mimetype must be first entry and stored uncompressed
		$zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.spreadsheet');
		$zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

		$zip->addFromString('META-INF/manifest.xml', $manifest);
		$zip->addFromString('content.xml', $content);
		$zip->addFromString('styles.xml', $stylesXml);
		$zip->addFromString('meta.xml', $meta);

		$zip->close();

		$content = (string)file_get_contents($tempFile);
		unlink($tempFile);

		return $content;
	}

	private function generateCsv(Poll $poll, array $votes): string {
		$options = json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR);
		$voteData = json_decode($poll->getVotes(), true, 512, JSON_THROW_ON_ERROR);
		$numVoters = $poll->getNumVoters();
		$statusStr = $poll->getStatus() === Poll::STATUS_CLOSED ? 'closed' : 'open';
		$hasDetails = !empty($votes);

		$output = fopen('php://memory', 'r+');

		// Summary section
		fputcsv($output, ['question', $this->escapeFormulae($poll->getQuestion())], escape: '');
		fputcsv($output, ['total-voters', (string)$numVoters], escape: '');
		fputcsv($output, ['status', $statusStr], escape: '');
		fputcsv($output, [], escape: '');

		// Options table
		fputcsv($output, ['option', 'votes', 'percentage'], escape: '');
		foreach ($options as $index => $option) {
			$count = $voteData[$index] ?? 0;
			$percentage = $numVoters > 0 ? round(($count / $numVoters) * 100, 1) : 0;
			fputcsv($output, [$this->escapeFormulae($option), (string)$count, (string)$percentage], escape: '');
		}

		// Voter details section
		if ($hasDetails) {
			fputcsv($output, [], escape: '');
			fputcsv($output, ['voter', 'option'], escape: '');
			foreach ($votes as $vote) {
				$voterName = $vote->getDisplayName() ?? '';
				$optionText = $options[$vote->getOptionId()] ?? '';
				fputcsv($output, [$this->escapeFormulae($voterName), $this->escapeFormulae($optionText)], escape: '');
			}
		}

		rewind($output);
		$content = (string)stream_get_contents($output);
		fclose($output);

		return $content;
	}

	protected function escapeFormulae(string $value): string {
		if (preg_match('/^[=+\-@\t\r]/', $value)) {
			return "'" . $value;
		}
		return $value;
	}
}
