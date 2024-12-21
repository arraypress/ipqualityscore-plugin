<?php return array(
    'root' => array(
        'name' => 'arraypress/ipqualityscore-plugin',
        'pretty_version' => 'dev-master',
        'version' => 'dev-master',
        'reference' => 'f98e740b752bdd9cc56b85e49e9027311fd24742',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'arraypress/ipqualityscore' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '993f6250cecd3c02cc3a9bdc173a775bc19dedf8',
            'type' => 'library',
            'install_path' => __DIR__ . '/../arraypress/ipqualityscore',
            'aliases' => array(
                0 => '9999999-dev',
            ),
            'dev_requirement' => false,
        ),
        'arraypress/ipqualityscore-plugin' => array(
            'pretty_version' => 'dev-master',
            'version' => 'dev-master',
            'reference' => 'f98e740b752bdd9cc56b85e49e9027311fd24742',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
