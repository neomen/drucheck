#!/bin/bash
cd  /downloads/drupal/web/modules/custom/workspace
# Default variable values
fix_mode=false
output_file=""

# Function to display script usage
usage() {
 echo "Usage: $0 [OPTIONS]"
 echo "Options:"
 echo " -h, --help      Display this help message"
 echo " -v, --version   Display version"
 echo " -f, --fix       Automatically fix, where possible, problems reported by rules."
}
# Function to display version
version() {
  echo "Druchek version: D10"
}
# Print separator line
separator() {
echo "
$1
-----------------------------------------------------------------------------
";
}

has_argument() {
  [[ ("$1" == *=* && -n ${1#*=}) || ( ! -z "$2" && "$2" != -*)  ]];
}

extract_argument() {
  echo "${2:-${1#*=}}"
}

# Function to handle options and arguments
handle_options() {
  while [ $# -gt 0 ]; do
    case $1 in
      -h | --help)
        usage
        exit 0
        ;;
      -v | --version)
        version
        exit 0
        ;;
      -f | --fix)
        fix_mode=true
        ;;
      *)
        echo "Invalid option: $1" >&2
        usage
        exit 1
        ;;
    esac
    shift
  done
}

# Main script execution
handle_options "$@"

# Perform the desired actions based on the provided flags and arguments
if [ "$fix_mode" = true ]; then
#  echo "fix mode enabled."
 separator "fix mode enabled."
fi

# spell code
cspellFunction() {
  WORDS_FILE=_cspell_unrecognized_words.txt
  if [ ! -f .cspell.json ]; then
      echo "Use default .cspell.json from https://git.drupalcode.org/project/gitlab_templates/-/raw/main/assets/.cspell.json"
      cp /downloads/.cspell.json ./.cspell.json
      CSPELL_CONFIG=/downloads/drupal/web/modules/custom/workspace/.tmpcspell.json
  else
      echo "Use local .cspell.json from your code"
      CSPELL_CONFIG=/downloads/drupal/web/modules/custom/workspace/.tmpcspell.json
  fi
  cp ./.cspell.json .tmpcspell.json
  sed -i 's@web/core/misc/cspell/drupal-dictionary.txt@/downloads/drupal/web/core/misc/cspell/drupal-dictionary.txt@g' .cspell.json
  sed -i 's@web/core/misc/cspell/dictionary.txt@/downloads/drupal/web/core/misc/cspell/dictionary.txt@g' .cspell.json
  sed -i 's@        "web",@@g' .cspell.json
  cspell-cli --show-suggestions --show-context --no-progress .
  echo "Generating a file _cspell_unrecognized_words.txt"
  cspell-cli --words-only --unique --no-progress . | sort -f -o $WORDS_FILE || true
  if [ "$fix_mode" = true ]; then
    if [ ! -f .cspell-project-words.txt ]; then
      echo "Generating a file .cspell-project-words.txt"
      cp $WORDS_FILE .cspell-project-words.txt
    fi
  fi

  rm .cspell.json
  cp ./.tmpcspell.json .cspell.json
  rm .tmpcspell.json
  touch $WORDS_FILE
  echo "List a file _cspell_unrecognized_words.txt"
  cat $WORDS_FILE
  # cp _cspell_unrecognized_words.txt .cspell-project-words.txt
  echo "Generating a file .cspell.json.txt from file .cspell.json";
  php /downloads/prepare-cspell.php /downloads/drupal/web/modules/custom/workspace/.cspell.json /downloads/drupal/web/modules/custom/workspace/.cspell.json.txt || true
}

composerValidateFunction() {
  if [ ! -f .composer.json ]; then
      echo "NO FILE composer.json "
  else
      composer validate;
  fi
}

parallelLintFunction() {
  parallel-lint --exclude .git --exclude vendor .
}

csDrupalPracticeFunction() {
  phpcs --standard=DrupalPractice --extensions='php,module,inc,install,test,profile,theme,css,info,txt,md' -q .
  if [ "$fix_mode" = true ]; then
    phpcbf --standard=DrupalPractice --extensions='php,module,inc,install,test,profile,theme,css,info,txt,md' -q .
  fi
}

csDrupalStandardFunction() {
  phpcs --standard=Drupal --extensions='php,module,inc,install,test,profile,theme' -q .
  if [ "$fix_mode" = true ]; then
    phpcbf --standard=Drupal --extensions='php,module,inc,install,test,profile,theme' -q .
  fi
}

phpstanAnalyzeFunction() {
  phpstan analyze . --configuration=/downloads/phpstan.neon --no-progress --memory-limit=256M
  if [ "$fix_mode" = true ]; then
    # TODO
    echo "https://github.com/rectorphp/rector"
  fi
}

stylelintFunction() {
  stylelint --ignore-path ./.stylelintignore --formatter verbose --config /downloads/drupal/web/core/.stylelintrc.json ./**/*.css --color
  if [ "$fix_mode" = true ]; then
    stylelint --fix --ignore-path ./.stylelintignore --formatter verbose --config /downloads/drupal/web/core/.stylelintrc.json ./**/*.css --color
  fi
}

separator "CSPELL CODE"
cspellFunction
separator "COMPOSER VALIDATE"
composerValidateFunction
separator "PARALLEL LINT"
parallelLintFunction
separator "CHECK DRUPAL BEST PRACTICE"
csDrupalPracticeFunction
separator "CHECK DRUPAL STANDARD"
csDrupalStandardFunction
separator "PHPSTAN ANALYZE"
phpstanAnalyzeFunction
separator "STYLELINT ANALYZE"
stylelintFunction