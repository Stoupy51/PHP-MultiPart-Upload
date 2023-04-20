
You should configurate post_max_size and upload_max_filesize in php.ini to allow upload large files.
example:

upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
max_execution_time = 3600

