# CSV to VuePress Markdown

A Symfony Console Command that create a command to generate VuePress Markdown files from CSVs.

## To Use

```markdown
git clone git@github.com:thinktandem/csv-to-vuepress-md.git
cd csv-to-vuepress-md
composer install
bin/console generate:md FILE_PATH
```

### Requirements

The file path is required. It will error out without it.

Your CSV **MUST** have a title, url, & body columns and header in it for this to work,

### Questions

Currently, we ask 2 questions

1. What delimiter do you want to use (defaults to ,)
2. Do you want to process textile formatted body field text (defaults to FALSE)

If you do process textile text, it will convert it to HTML first then to markdown.

### YAML Frontmatter

All columns that are not title, url, & body will be generated as [YAML Frontmatter](https://vuepress.vuejs.org/guide/frontmatter.html#predefined-variables).

### Content

Textile text (if chosen) is converted to HTML via [PHP-Textile](https://github.com/textile/php-textile).

Most HTML is converted to Markdown & Markdown Extra via [Markdownify](https://github.com/Elephant418/Markdownify).

### Output

Currently all files are generated to the output folder.  You can just copy the contents of that into your VuePress setup.
