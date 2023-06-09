<?php

$project = basename(realpath('.'));
$vendor = readline('What is the name of the vendor: ');
if (empty(trim($vendor))) {
    exit('A project needs a vendor name!');
}
$vendor = strtolower(preg_replace('/\s+/', '-', $vendor));
$customProject = readline("Do you want to change the project name? (Default: $project) [y|N]");
if ('y' === strtolower($customProject)) {
    $tmpProject = readline('Please enter your project name (Example: my-project): ');
    if (!preg_match('/^[a-z]+[a-z-]+[a-z$]/', $tmpProject)) {
        exit('Wrong project name pattern. Only lowercase and dashes are supported.');
    }
    $project = $tmpProject;
}
$description = readline('Describe your project in few words: ');

echo "Vendor is: $vendor" . PHP_EOL;
echo "Project is: $project" . PHP_EOL;
echo "Composer Name is: $vendor/$project" . PHP_EOL;
echo "Description is: $description" . PHP_EOL;

$hostname = readline('Please enter your local dev hostname: ');
$mail = readline('Please enter a valid mail for SSL Certs: ');

$replaces = [
    '{{ vendor }}' => $vendor,
    '{{ project }}' => $project,
    '{{ description }}' => $description,
    '{{ hostname }}' => $hostname,
    '{{ email }}' => $mail,
];

$filenameReplaces = [
    'editorconfig' => '.editorconfig',
    'env' => '.env',
];

exec (
    'docker run --rm -v $(pwd)/tmpl/files/docker/nginx/etc/certs:/tmp/certs -w /tmp/certs debian:latest bash -c "'
    . 'apt-get update && apt-get install -y openssl'
    . ' && openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 '
    . '-subj "/C=US/ST=NC/L=Local/O=Dev/CN='. $hostname .'" -keyout ./'. $hostname .'.key -out ./'. $hostname .'.crt'
    . ' && openssl dhparam -out ./dhparam.pem 2048"'
);

function replaceCustoms($target, array $replaces)
{
    if (is_dir($target)) {
        foreach (glob($target.'/*') as $files) {
            replaceCustoms($files, $replaces);
        }
        return;
    }

    echo "Preparing defaults file $target..." . PHP_EOL;
    file_put_contents(
        $target,
        strtr(
            file_get_contents($target),
            $replaces
        )
    );
}

foreach (glob('tmpl/files/*', GLOB_BRACE) as $distFile) {
    $target = false === strpos($distFile, '-dist')
        ? pathinfo($distFile, PATHINFO_BASENAME)
        : substr(pathinfo($distFile, PATHINFO_FILENAME), 0, -5) . '.' . pathinfo($distFile, PATHINFO_EXTENSION);

    echo "Move $distFile to $target..." . PHP_EOL;
    rename($distFile, $target);

    if (is_file($target) && array_key_exists($target, $filenameReplaces)) {
        $oldTarget = $target;
        $target = $filenameReplaces[$target];

        echo "Move $oldTarget to $target..." . PHP_EOL;
        rename($oldTarget, $target);
    }

    replaceCustoms($target, $replaces);
}

echo "Creating public/ src/ and tests/ directory" . PHP_EOL;
mkdir('public');
mkdir('src');
mkdir('tests');

function removeTemplate($path)
{
    foreach (glob($path . '/*', GLOB_BRACE) as $value) {
        if (in_array($value, ['.', '..'])) {
            continue;
        }
        is_dir($value) ? removeTemplate($value) : unlink($value);
    }

    rmdir($path);
}

echo "Checking docker..." . PHP_EOL;
exec('docker --version', $out, $code);
$dockerInstalled = 0 === $code;
if (!$dockerInstalled) {
    echo "It is recommended that you have installed docker.";
}
exec('docker-compose --version', $out, $code);
$dockerComposeInstalled = 0 === $code;

if (!$dockerComposeInstalled) {
    echo "It is recommended that you have installed docker-compose.";
}

echo "Project setup finished..." . PHP_EOL;
echo "Removing template files..." . PHP_EOL;
removeTemplate('tmpl');
