<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Markdownify\ConverterExtra;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Netcarver\Textile\Parser;

class GenerateMdCommand extends Command {

  /**
   * @var string
   */
  protected static $defaultName = 'generate:md';

  /**
   * @var array
   */
  protected $header = [];

  /**
   * @var array
   */
  protected $rows = [];

  /**
   * @var array
   */
  protected $currentRow;

  /**
   * @var string
   */
  protected $urlKey;

  /**
   * @var string
   */
  protected $bodyKey;

  /**
   * @var string
   */
  protected $titleKey;

  /**
   * @var string
   */
  protected $filePath;

  /**
   * @var bool
   */
  protected $createHeader = TRUE;

  /**
   * @var string
   */
  protected $textileKey;

  /**
   * @inheritdoc
   */
  protected function configure() {
      $this
        ->setDescription('Generates a VuePress MD file from a CSV')
        ->addArgument('filename', InputArgument::REQUIRED, 'Path to CSV File (ex: ./fancy.csv)');
  }

  /**
   * @inheritdoc
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    // Start the helped
    $helper = $this->getHelper('question');

    // Ask deliminator question
    $dquestion = new Question('CSV delimiter (defaults to ,): ', ',');
    $delim = $helper->ask($input, $output, $dquestion);

    // Ask Header Question
    $hquestion = new ConfirmationQuestion('Create a H1 Header on each page from the title column (y/n): ', TRUE);
    $this->createHeader = $helper->ask($input, $output, $hquestion);

    $io = new SymfonyStyle($input, $output);
    if ($filename = $input->getArgument('filename')) {
      if (($handle = fopen($filename, "rb")) !== FALSE) {
        $i = 0;
        while (($data = fgetcsv($handle, null, $delim)) !== FALSE) {
          $i++;
          if ($i === 1) {
            $this->header = $data;
            continue;
          }
          $this->rows[] = $data;
        }
        fclose($handle);
      }

      // Make sure we have body and url fields.
      if (!$this->validateHeader()) {
        $io->error("Your CSV file does not contain the required header keys of body, title, and/or url");
        return;
      }

      // Make sure we got stuffs to process.
      if (empty($this->rows)) {
        $io->error("Your CSV contains no data");
        return;
      }

      // Engage.
      $this->processRows();
      $io->success("All files have been generated to the ./output directory");
    }
    else {
      $io->error("Please provide a filename or pipe template content to STDIN.");
      return;
    }
  }

  /**
   * Check is the required header keys are there.
   *
   * @return bool
   */
  protected function validateHeader() {
    return count(array_intersect($this->header, ['body', 'url', 'title'])) == count(['body', 'url', 'title']);
  }

  /**
   * Processes the Rows of stuffs.
   */
  protected function processRows() {
    $this->bodyKey = array_search('body', $this->header);
    $this->urlKey = array_search('url', $this->header);
    $this->titleKey = array_search('title', $this->header);
    $this->textileKey = array_search('textile', $this->header);

    // Engage.
    foreach ($this->rows as $row) {
      $this->currentRow = $row;
      $this->createDirectoryStructureAndFile();
      $this->generateFrontMatter();
      $this->generateContent();
    }
  }

  /**
   * Creates the directories and files.
   */
  protected function createDirectoryStructureAndFile() {
    // We set the path to be all folder so urls don't have html on them.
    $output = './output/' . $this->currentRow[$this->urlKey];

    // Create the directories as need be.
    if (!is_dir($output)){
      mkdir($output, 0755, true);
    }

    // Now create our file.
    $this->filePath = $output . '/README.md';
    if (!file_exists($this->filePath)) {
      $file = fopen($this->filePath, 'wb');
      fwrite($file, '');
      fclose($file);
    }

    // URL key is no longer needed.
    unset($this->currentRow[$this->urlKey]);
  }

  /**
   * Generate the front matter.
   */
  protected function generateFrontMatter() {
    // Set our title then banish it.
    $title = '"' . str_replace('"', '', $this->currentRow[$this->titleKey]) . '"';
    unset($this->currentRow[$this->titleKey]);

    $file = fopen($this->filePath, 'wb');
    fwrite($file, "---\n");
    fwrite($file, "title: " . $title . "\n");

    // Write all other keys.
    foreach ($this->currentRow as $key => $value) {
      if ($key === $this->bodyKey || $key === $this->textileKey) {
        continue;
      }

      fwrite($file, $this->header[$key] . ": " . $value . "\n");
      unset($this->currentRow[$key]);
    }

    fwrite($file, "---\n");
    fwrite($file, "\n");

    // Add header if asked.
    if ($this->createHeader) {
      fwrite($file, "# " . str_replace('"', '', $title));
      fwrite($file, "\n");
    }

    fclose($file);
  }

  /**
   * Converts our html to markdown
   */
  protected function generateContent() {
    $body = $this->currentRow[$this->bodyKey];

    // Change newline to line breaks.
    $body = str_replace('\n','<br>', $body);

    // Convert HTML to Markdown.
    $converter = new ConverterExtra();
    $converter->setKeepHTML(FALSE);
    $body = $converter->parseString($body);

    // If textile formatted.
    if ($this->currentRow[$this->textileKey] === 'Y') {
      $body = (new Parser())->parse($body);
      $body = str_replace("%5Cn", '', $body);
    }

    // Now write the body content.
    $file = fopen($this->filePath, 'ab');
    fwrite($file, "\n");
    fwrite($file, $body);
    fclose($file);
  }
}
