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
	 * Generate a spreadsheet file (XLSX or ODS) from poll data.
	 *
	 * @param Poll $poll The poll to export
	 * @param list<Vote> $votes Detailed votes (empty for hidden polls)
	 * @param 'xlsx'|'ods' $format The output format
	 * @return string The file content
	 */
	public function exportToSpreadsheet(Poll $poll, array $votes, string $format): string {
		if ($format === 'ods') {
			return $this->generateOds($poll, $votes);
		}
		return $this->generateXlsx($poll, $votes);
	}

	private function generateXlsx(Poll $poll, array $votes): string {
		$options = json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR);
		$voteData = json_decode($poll->getVotes(), true, 512, JSON_THROW_ON_ERROR);
		$numVoters = $poll->getNumVoters();
		$hasDetails = !empty($votes);

		// Build shared strings table
		$strings = [];
		$stringIndex = [];
		$addString = function (string $s) use (&$strings, &$stringIndex): int {
			if (!isset($stringIndex[$s])) {
				$stringIndex[$s] = count($strings);
				$strings[] = $s;
			}
			return $stringIndex[$s];
		};

		// Pre-register all strings
		$addString('Question');
		$addString($poll->getQuestion());
		$addString('Total voters');
		$addString('Status');
		$addString($poll->getStatus() === Poll::STATUS_CLOSED ? 'Closed' : 'Open');
		$addString('Option');
		$addString('Votes');
		$addString('Percentage');
		foreach ($options as $option) {
			$addString($option);
		}
		if ($hasDetails) {
			$addString('Voter');
			foreach ($votes as $vote) {
				$addString($vote->getDisplayName() ?? '');
				$addString($options[$vote->getOptionId()] ?? '');
			}
		}

		// Build shared strings XML
		$sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
		foreach ($strings as $s) {
			$sharedStringsXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
		}
		$sharedStringsXml .= '</sst>';

		// Build Sheet 1 (Summary)
		$sheet1 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetData>';

		// Row 1: Question label + question text
		$sheet1 .= '<row r="1">'
			. '<c r="A1" t="s" s="1"><v>' . $addString('Question') . '</v></c>'
			. '<c r="B1" t="s"><v>' . $addString($poll->getQuestion()) . '</v></c>'
			. '</row>';

		// Row 2: Total voters
		$sheet1 .= '<row r="2">'
			. '<c r="A2" t="s" s="1"><v>' . $addString('Total voters') . '</v></c>'
			. '<c r="B2"><v>' . $numVoters . '</v></c>'
			. '</row>';

		// Row 3: Status
		$statusStr = $poll->getStatus() === Poll::STATUS_CLOSED ? 'Closed' : 'Open';
		$sheet1 .= '<row r="3">'
			. '<c r="A3" t="s" s="1"><v>' . $addString('Status') . '</v></c>'
			. '<c r="B3" t="s"><v>' . $addString($statusStr) . '</v></c>'
			. '</row>';

		// Row 4: empty

		// Row 5: Headers
		$sheet1 .= '<row r="5">'
			. '<c r="A5" t="s" s="1"><v>' . $addString('Option') . '</v></c>'
			. '<c r="B5" t="s" s="1"><v>' . $addString('Votes') . '</v></c>'
			. '<c r="C5" t="s" s="1"><v>' . $addString('Percentage') . '</v></c>'
			. '</row>';

		// Data rows
		$row = 6;
		foreach ($options as $index => $option) {
			$count = $voteData[$index] ?? 0;
			$percentage = $numVoters > 0 ? round(($count / $numVoters) * 100, 1) : 0;
			$col = $this->xlsxColumnLetter(0);
			$sheet1 .= '<row r="' . $row . '">'
				. '<c r="A' . $row . '" t="s"><v>' . $addString($option) . '</v></c>'
				. '<c r="B' . $row . '"><v>' . $count . '</v></c>'
				. '<c r="C' . $row . '"><v>' . $percentage . '</v></c>'
				. '</row>';
			$row++;
		}

		$sheet1 .= '</sheetData></worksheet>';

		// Build Sheet 2 (Votes) if details available
		$sheet2 = null;
		if ($hasDetails) {
			$sheet2 = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
				. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
				. '<sheetData>';

			// Header row
			$sheet2 .= '<row r="1">'
				. '<c r="A1" t="s" s="1"><v>' . $addString('Voter') . '</v></c>'
				. '<c r="B1" t="s" s="1"><v>' . $addString('Option') . '</v></c>'
				. '</row>';

			$row = 2;
			foreach ($votes as $vote) {
				$voterName = $vote->getDisplayName() ?? '';
				$optionText = $options[$vote->getOptionId()] ?? '';
				$sheet2 .= '<row r="' . $row . '">'
					. '<c r="A' . $row . '" t="s"><v>' . $addString($voterName) . '</v></c>'
					. '<c r="B' . $row . '" t="s"><v>' . $addString($optionText) . '</v></c>'
					. '</row>';
				$row++;
			}

			$sheet2 .= '</sheetData></worksheet>';
		}

		// Rebuild shared strings XML with all strings
		$sharedStringsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
		foreach ($strings as $s) {
			$sharedStringsXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></si>';
		}
		$sharedStringsXml .= '</sst>';

		// Content Types
		$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		if ($sheet2 !== null) {
			$contentTypes .= '<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$contentTypes .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
			. '</Types>';

		// Root rels
		$rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';

		// Workbook
		$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>'
			. '<sheet name="Summary" sheetId="1" r:id="rId1"/>';
		if ($sheet2 !== null) {
			$workbook .= '<sheet name="Votes" sheetId="2" r:id="rId2"/>';
		}
		$workbook .= '</sheets></workbook>';

		// Workbook rels
		$workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>';
		if ($sheet2 !== null) {
			$workbookRels .= '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>';
		}
		$workbookRels .= '<Relationship Id="rId' . ($sheet2 !== null ? '3' : '2') . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. '<Relationship Id="rId' . ($sheet2 !== null ? '4' : '3') . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
			. '</Relationships>';

		// Styles (minimal: bold font for headers)
		$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '</cellXfs>'
			. '</styleSheet>';

		// Create ZIP
		$tempFile = tempnam(sys_get_temp_dir(), 'poll_export_');
		$zip = new ZipArchive();
		$zip->open($tempFile, ZipArchive::OVERWRITE);

		$zip->addFromString('[Content_Types].xml', $contentTypes);
		$zip->addFromString('_rels/.rels', $rootRels);
		$zip->addFromString('xl/workbook.xml', $workbook);
		$zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
		$zip->addFromString('xl/styles.xml', $styles);
		$zip->addFromString('xl/sharedStrings.xml', $sharedStringsXml);
		$zip->addFromString('xl/worksheets/sheet1.xml', $sheet1);
		if ($sheet2 !== null) {
			$zip->addFromString('xl/worksheets/sheet2.xml', $sheet2);
		}

		$zip->close();

		$content = file_get_contents($tempFile);
		unlink($tempFile);

		return $content;
	}

	private function generateOds(Poll $poll, array $votes): string {
		$options = json_decode($poll->getOptions(), true, 512, JSON_THROW_ON_ERROR);
		$voteData = json_decode($poll->getVotes(), true, 512, JSON_THROW_ON_ERROR);
		$numVoters = $poll->getNumVoters();
		$hasDetails = !empty($votes);
		$statusStr = $poll->getStatus() === Poll::STATUS_CLOSED ? 'Closed' : 'Open';

		$esc = function (string $s): string {
			return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
		};

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
				. '<table:table-cell office:value-type="float" office:value="' . $count . '"><text:p>' . $count . '</text:p></table:table-cell>'
				. '<table:table-cell office:value-type="float" office:value="' . $percentage . '"><text:p>' . $percentage . '</text:p></table:table-cell>'
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

		$content = file_get_contents($tempFile);
		unlink($tempFile);

		return $content;
	}

	private function xlsxColumnLetter(int $index): string {
		return chr(65 + $index);
	}
}
