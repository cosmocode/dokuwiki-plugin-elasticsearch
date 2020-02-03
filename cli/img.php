<?php
/**
 * DokuWiki Plugin elasticsearch (CLI Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Andreas Gohr <gohr@cosmocode.de>
 */

if (!defined('DOKU_INC')) die();

use splitbrain\phpcli\Options;

/**
 * CLI tools for managing the index
 */
class cli_plugin_elasticsearch_img extends DokuWiki_CLI_Plugin
{

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     * @throws \splitbrain\phpcli\Exception
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Output image data using DokuWiki\'s builtin EXIF capabilities');

        $options->registerArgument('file', 'image file to convert to text', true);
    }

    /** * @inheritDoc */
    protected function main(Options $options)
    {
        $args = $options->getArgs();

        $meta = new JpegMeta($args[0]);

        $data = [
            'title' => $meta->getTitle(0),
            'content' => join("\n", [
                $meta->getField([
                    'Iptc.Caption',
                    'Exif.UserComment',
                    'Exif.ImageDescription',
                    'Exif.TIFFImageDescription',
                    'Exif.TIFFUserComment',
                ]),
                $meta->getField([
                    'Iptc.Byline',
                    'Exif.TIFFArtist',
                    'Exif.Artist',
                    'Iptc.Credit',
                ]),
                $meta->getField([
                    'Iptc.Keywords',
                    'Exif.Category',
                ]),
                $meta->getField([
                    'Iptc.CopyrightNotice',
                    'Exif.TIFFCopyright',
                    'Exif.Copyright',
                ]),
            ]),
            'created' => date('Y-m-d\TH:i:s\Z', $meta->getField('Date.EarliestTime')),
        ];

        echo json_encode($data, JSON_PRETTY_PRINT);
    }

}
