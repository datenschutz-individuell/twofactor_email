<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Olav and Niklas Seyfarth, Contributors <https://github.com/datenschutz-individuell/twofactor_email/blob/main/CONTRIBUTORS.md>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TwoFactorEMail\Test\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * A schema migration and a pre- or post-migration repair step run inside the process
 * that updated the app, and that process still has the previous version's classes
 * loaded: the files on disk are new, the declared classes are not. Touching a class of
 * this app from there therefore runs new code against an old class — which killed the
 * update to 3.5.0 with "Cannot access private constant AppSettings::KEY_EMAIL_SUBJECT".
 *
 * A live migration is queued as a background job and runs in a fresh process, so it
 * sees the new classes. This test holds the two rules that follow: a repair step that
 * uses anything from this app is a live migration, and a schema migration — which has
 * no such escape, MigrationService always runs it in that same process — uses nothing
 * from this app at all.
 */
final class RepairStepProcessTest extends TestCase {
	private const ROOT = __DIR__ . '/../../..';

	/** The phases that run in the process that performed the update. */
	private const STALE_PHASES = ['pre-migration', 'post-migration'];

	public function testStepsUsingAppClassesAreLiveMigrations(): void {
		// Read and parse in two steps: Nextcloud's bootstrap installs an entity loader
		// that returns null, and libxml then refuses to load the document itself, so
		// simplexml_load_file() fails under the app's own test suite.
		$xml = file_get_contents(self::ROOT . '/appinfo/info.xml');
		self::assertNotFalse($xml, 'appinfo/info.xml could not be read');

		$info = simplexml_load_string($xml);
		self::assertNotFalse($info, 'appinfo/info.xml could not be parsed');

		$steps = $info->{'repair-steps'};
		self::assertNotEmpty($steps, 'no repair steps found — has the element been renamed?');

		foreach ($steps->children() as $phase => $phaseSteps) {
			// An install or uninstall step is not affected: an install has no previous
			// version, and an uninstall cannot be a background job, because the app is
			// gone before the job would run.
			if (!in_array($phase, self::STALE_PHASES, true)) {
				continue;
			}
			foreach ($phaseSteps->step as $step) {
				$class = (string)$step;
				self::assertSame(
					[],
					$this->appClassesUsedBy($this->sourceOf($class)),
					$class . ' uses classes of this app, so it must be a live migration and not run in the '
					. 'process that updated the app, which still holds the previous version of those classes.',
				);
			}
		}
	}

	public function testSchemaMigrationsUseNoAppClasses(): void {
		$files = glob(self::ROOT . '/lib/Migration/Version*.php');
		self::assertNotFalse($files);

		foreach ($files as $file) {
			$source = file_get_contents($file);
			self::assertNotFalse($source);
			self::assertSame(
				[],
				$this->appClassesUsedBy($source),
				basename($file) . ' uses classes of this app. A schema migration always runs in the process '
				. 'that performed the update, where those classes are still the previous version — spell the '
				. 'values out instead.',
			);
		}
	}

	private function sourceOf(string $class): string {
		$file = self::ROOT . '/lib' . str_replace(['OCA\\TwoFactorEMail', '\\'], ['', '/'], $class) . '.php';
		self::assertFileExists($file, $class . ' is registered as a repair step but has no source file');

		$source = file_get_contents($file);
		self::assertNotFalse($source);

		return $source;
	}

	/** The source without comments and string contents, so only real code is read. */
	private function codeOnly(string $source): string {
		$ignored = [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML];

		$code = '';
		foreach (token_get_all($source) as $token) {
			$code .= is_array($token) ? (in_array($token[0], $ignored, true) ? '' : $token[1]) : $token;
		}

		return $code;
	}

	/**
	 * The classes of this app that the given source names. Imports are read as written;
	 * a name that is neither imported nor written with a leading backslash resolves to
	 * the file's own namespace, which is this app — so a sibling used without an import
	 * is found as well.
	 *
	 * @return list<string>
	 */
	private function appClassesUsedBy(string $source): array {
		$source = $this->codeOnly($source);
		$namespace = preg_match('~^namespace\s+([^;]+);~m', $source, $match) === 1 ? $match[1] . '\\' : '';

		preg_match_all('~^use\s+([^\s;]+)(?:\s+as\s+(\w+))?;~m', $source, $imports, PREG_SET_ORDER);
		$imported = [];
		foreach ($imports as $import) {
			$imported[$import[2] ?? substr(strrchr('\\' . $import[1], '\\'), 1)] = $import[1];
		}

		// Every way a class is named in a body: static access, instantiation, a type in
		// front of a variable, an instanceof test. A leading backslash means the global
		// namespace and is not ours.
		preg_match_all(
			'~(?<!\\\\)\b(?:new\s+|instanceof\s+)?([A-Z]\w*)(?=::|\s*\(|\s+\$|\s*;)~',
			$source,
			$used,
		);

		$found = [];
		foreach (array_unique($used[1]) as $name) {
			if (in_array($name, ['self', 'static', 'parent'], true)) {
				continue;
			}
			$resolved = $imported[$name] ?? $namespace . $name;
			if (str_starts_with($resolved, 'OCA\\TwoFactorEMail\\')) {
				$found[] = $resolved;
			}
		}
		sort($found);

		return $found;
	}
}
