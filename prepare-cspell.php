#!/usr/bin/env php
<?php
// origin file https://git.drupalcode.org/project/gitlab_templates/-/raw/main/scripts/prepare-cspell.php
/**
 * @file
 * Prepares a .cspell.json file customized for the gitlab templates environment.
 *
 * Param 1 = test_suffix (optional) - Additional suffix to append to the input
 *   filename, before writing out. This is used when running the script locally
 *   during development, to avoid overwriting the input .cspell.json file.
 */

// Get the contents of .cspell.json into an array. This file will be either the
// projects own .cspell.json or the default copied from /assets.
$cspell_filename = $argv[1] ?? '.cspell.json';
$output_cspell_filename = $argv[2] ?? '.cspell.json.txt';
$cspell_json = json_decode(file_get_contents($cspell_filename), TRUE);
if (empty($cspell_json)) {
  throw new RuntimeException("Unable to read $cspell_filename");
}

// // Allow for easy testing by avoiding overwriting the input file.
// $test_suffix = $argv[1] ?? '.cspell.json';
// // TODO fix it from origin file.
// $cspell_filename = $test_suffix;

$webRoot = getenv('_WEB_ROOT') ?: 'web';

// Some directories in the project root are not part of the project.
$non_project_directories = ["$webRoot", 'vendor', 'node_modules', '.git'];

// -----
// Words
// -----
//
// The module's machine name might not be a real word, so add this. The value of
// $CI_PROJECT_NAME can end -nnnnnnn so remove this part.
$words = stristr(getenv('CI_PROJECT_NAME') . '-', '-', TRUE);

// Get the words from $_CSPELL_WORDS.
if ($cspell_words = getenv('_CSPELL_WORDS')) {
  // Remove any quotes and spaces. Double quotes are added in json_encode.
  $words .= ',' . str_replace(["'", '"', ' '], ['', '', ''], $cspell_words);
}

// Module names can be made up of constituent parts that fail spell check.
// Therefore find of all the sub-modules by looking for .info.yml files, and
// add each name part to the allowed list.
$sub_module_name_parts = [];
// Also use this RecursiveDirectoryIterator to find the actual case-sensitive
// names of these standard files that can be ignored.
$filenames_to_find = [
  'license',
  'copyright',
  'maintainers',
  'changelog',
  'composer',
];
// Initialize the array with these fixed file names.
$ignore_files = [
  '**/.*.json',
  'package.json',
  'yarn.lock',
  'phpstan*',
  '.*ignore',
];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
  // Ignore everything in these folders.
  foreach ($non_project_directories as $dir) {
    if (stripos($file, "./$dir/") !== FALSE) {
      continue 2;
    }
  }
  if (stripos($file->getFilename(), '.info.yml') !== FALSE) {
    // Get each underscore-separated part of the module name.
    $sub_module_name_parts = array_merge(
      $sub_module_name_parts,
      explode('_', str_replace('.info.yml', '', $file->getFilename())),
    );
  }
  // Identify files with a name in this list, regardless of case or extension.
  $filename_without_ext = strtolower(substr($file->getFilename(), 0, strpos($file->getFilename(), '.')));
  if (in_array($filename_without_ext, $filenames_to_find)) {
    $ignore_files[] = $file->getPathname();
  }
}

// Merge into the existing json 'words' value, but cater for that being empty.
// array_values() is needed after array_unique() to restore the keys to numeric.
$cspell_json['words'] = array_values(array_unique(array_merge(
  $cspell_json['words'] ?? [],
  array_filter(explode(',', $words)),
  $sub_module_name_parts,
  // Add lando and ddev which appear in the original version of phpcs.xml.dist
  // See https://www.drupal.org/project/gitlab_templates/issues/3427357#comment-15487034
  ['lando', 'ddev'],
)));

// ----------
// Flag Words
// ----------
//
// Get any flagged words from $_CSPELL_FLAGWORDS.
if ($cspell_flagwords = getenv('_CSPELL_FLAGWORDS')) {
  // Remove any quotes and spaces. Double quotes are added in json_encode.
  $cspell_flagwords = str_replace(["'", '"', ' '], ['', '', ''], $cspell_flagwords);
  $cspell_json['flagWords'] = array_values(array_unique(array_merge(
    $cspell_json['flagWords'] ?? [],
    array_filter(explode(',', $cspell_flagwords)),
  )));
}

// ------------
// Ignore Paths
// ------------
//
// Ignore the paths in the project root folder that are not part of the project.
$paths = $non_project_directories;
// Add these commonly ignored paths and files if required.
$cspell_ignore_standard_files = getenv('_CSPELL_IGNORE_STANDARD_FILES');
if ("$cspell_ignore_standard_files" != "0") {
  $paths = array_merge($paths, $ignore_files);
}
// Add the values from $_CSPELL_IGNORE_PATHS, removing quotes and spaces.
if ($cspell_ignore_paths = getenv('_CSPELL_IGNORE_PATHS')) {
  $cspell_ignore_paths = str_replace(["'", '"', ' '], ['', '', ''], $cspell_ignore_paths);
}
$cspell_json['ignorePaths'] = array_values(array_unique(array_merge(
  $cspell_json['ignorePaths'] ?? [],
  $paths,
  $cspell_ignore_paths ? explode(',', $cspell_ignore_paths) : [],
)));

// ------------
// Dictionaries
// ------------
$dictionary_definitions = [
  [
    'name' => 'drupal',
    'path' => $webRoot . '/core/misc/cspell/drupal-dictionary.txt',
  ],
  [
    'name' => 'dictionary',
    'path' => $webRoot . '/core/misc/cspell/dictionary.txt',
  ],
  [
    'name' => 'project-words',
    'path' => './.cspell-project-words.txt',
    'description' => "The project's own custom dictionary (optional)",
  ],
];
$dictionary_names = [];
foreach ($dictionary_definitions as $data) {
  $dictionary_names[] = $data['name'];
}
// These dictionaries are provided by cspell.
$built_in_dictionaries = [
  'companies',
  'fonts',
  'html',
  'php',
  'softwareTerms',
  'misc',
  'typescript',
  'node',
  'css',
  'bash',
  'filetypes',
  'npm',
  'lorem-ipsum',
];
$cspell_json['dictionaries'] = array_values(array_unique(array_merge(
  $cspell_json['dictionaries'] ?? [],
  $built_in_dictionaries,
  $dictionary_names,
)));

// Remove any matching entries so that the updated versions take precedence.
foreach ($cspell_json['dictionaryDefinitions'] ?? [] as $key => $dic) {
  if (in_array($dic['name'], $dictionary_names)) {
    unset($cspell_json['dictionaryDefinitions'][$key]);
  }
}
$cspell_json['dictionaryDefinitions'] = merge_deep($dictionary_definitions, $cspell_json['dictionaryDefinitions'] ?? []);

// ---------------------------
// Write out the modified file
// ---------------------------
file_put_contents($output_cspell_filename, json_encode($cspell_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

/**
 * Deeply merges arrays. Borrowed from Drupal core.
 */
function merge_deep(): array {
  return merge_deep_array(func_get_args());
}

/**
 * Deeply merges arrays. Borrowed from drupal.org/project/core.
 *
 * @param array $arrays
 *   An array of array that will be merged.
 * @param bool $preserve_integer_keys
 *   Whether to preserve integer keys.
 */
function merge_deep_array(array $arrays, bool $preserve_integer_keys = FALSE): array {
  $result = [];
  foreach ($arrays as $array) {
    foreach ($array as $key => $value) {
      if (is_int($key) && !$preserve_integer_keys) {
        $result[] = $value;
      }
      elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
        $result[$key] = merge_deep_array([$result[$key], $value], $preserve_integer_keys);
      }
      else {
        $result[$key] = $value;
      }
    }
  }
  return $result;
}
