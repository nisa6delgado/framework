<?php

use Aws\S3\S3MultiRegionClient;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FTP\FtpAdapter;
use League\Flysystem\FTP\FtpConnectionOptions;
use League\Flysystem\FTP\FtpConnectionProvider;
use League\Flysystem\FTP\NoopCommandConnectivityChecker;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV2\SftpAdapter;
use League\Flysystem\PhpseclibV2\SftpConnectionProvider;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use Spatie\Dropbox\Client;
use Spatie\FlysystemDropbox\DropboxAdapter;

class Storage
{
    /**
     * Instance of Filesystem
     *
     * @var Filesystem
     */
    public $instance;

    /**
     * Name of the previously uploaded file.
     *
     * @var string|array
     */
    public $filename;

    /**
     * Result of the previous operation.
     *
     * @var boolean
     */
    public $success;

    /**
     * Initialize the class and create the instance according to the given adapter.
     *
     * @param string $adapter
     * @return Filesystem
     */
    public function __construct($adapter = 'local')
    {
        if ($adapter == 'local') {
            $adapter        = new LocalFilesystemAdapter('/');
            $this->instance = new Filesystem($adapter);
        }

        // spatie/flysystem-dropbox
        if ($adapter == 'dropbox') {
            $client         = new Client(config('dropbox', 'access_token'));
            $adapter        = new DropboxAdapter($client);
            $this->instance = new Filesystem($adapter, ['case_sensitive' => false]);
        }

        // league/flysystem-aws-s3-v3
        if ($adapter == 's3') {
            $client = new S3MultiRegionClient([
                'credentials' => [
                    'key'    => config('s3', 'key'),
                    'secret' => config('s3', 'secret'),
                ],
                'version'     => 'latest|version',
            ]);

            $adapter        = new AwsS3Adapter($client, config('s3', 'bucket'));
            $this->instance = new Filesystem($adapter);
        }

        // league/flysystem-ftp
        if ($adapter == 'ftp') {
            $adapter = new FtpAdapter(
                FtpConnectionOptions::fromArray([
                    'host'     => config('ftp', 'host'),
                    'root'     => config('ftp', 'root'),
                    'username' => config('ftp', 'username'),
                    'password' => config('ftp', 'password'),
                ]),
                new FtpConnectionProvider(),
                new NoopCommandConnectivityChecker(),
                new PortableVisibilityConverter()
            );

            $this->instance = new Filesystem($adapter);
        }

        // league/flysystem-sftp
        if ($adapter == 'sftp') {
            $this->instance = new Filesystem(new SftpAdapter(
                new SftpConnectionProvider(
                    config('sftp', 'localhost'),
                    config('sftp', 'username'),
                    config('sftp', 'password'),
                    config('sftp', 'private_key'),
                    config('sftp', 'passphrase'),
                    config('sftp', 'port'),
                    true,
                    30,
                    10,
                    'fingerprint-string',
                    null
                ),
                config('sftp', 'root'),
                PortableVisibilityConverter::fromArray([
                    'file' => [
                        'public'  => 0640,
                        'private' => 0604,
                    ],
                    'dir'  => [
                        'public'  => 0740,
                        'private' => 7604,
                    ],
                ])
            ));
        }
    }

    /**
     * Get resource of the file from any of the disks.
     *
     * @param string $adapter
     * @return void
     */
    public function get(string $get)
    {
        return $this->instance->readStream($get);
    }

    /**
     * Save resource on any of the disks.
     *
     * @param string $path
     * @param string $adapter
     * @param string $filename
     * @return Storage
     */
    public function save($path, $content, $filename = ''): Storage
    {
        if (is_string($content)) {
            if ($_FILES[$content]) {
                if (is_array($_FILES[$content]['name'])) {
                    $i = 0;

                    foreach ($_FILES[$content]['name'] as $item) {
                        $stream = fopen($_FILES[$content]['tmp_name'][$i], 'r');

                        $this->instance->writeStream($path . '/' . $_FILES[$content]['name'][$i], $stream);

                        $this->filename[] = $_FILES[$content]['name'][$i];
                        $this->success = true;

                        $i = $i + 1;
                    }

                } else {
                    $filename = ($filename != '') ? $filename : $_FILES[$content]['name'];
                    $stream   = fopen($_FILES[$content]['tmp_name'], 'r');

                    $this->instance->writeStream($path . '/' . $filename, $stream);

                    $this->filename = $filename;
                    $this->success = true;
                }
            }
        }

        if (is_resource($content)) {
            $stream = $content;
            $this->instance->writeStream($path . '/' . $filename, $stream);

            $this->filename = $filename;
            $this->success = true;
        }

        return $this;
    }

    /**
     * Delete resource of any of the disks.
     *
     * @param string $path
     * @return void
     */
    public function delete(string $path): void
    {
        $this->instance->delete($path);
    }
}
