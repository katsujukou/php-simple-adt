{ pkgs ? import <nixpkgs> {}
}:

let php-with-extensions = pkgs.php.withExtensions ({ all, enabled }:
      enabled ++ (with all; [ xdebug ])
    );
in
  pkgs.mkShell {
    buildInputs = [
      php-with-extensions
      php-with-extensions.packages.composer2
    ];

    shellHook = ''
      echo 
      export PATH=$PWD/vendor/bin:$PATH
    '';
  }
