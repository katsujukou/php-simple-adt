{ pkgs ? import <nixpkgs> {}
}:
let
  php = pkgs.php.buildEnv {
    extensions = { all, ...}: with all; [
      mbstring
      json
      openssl
      tokenizer
      filter
      xdebug
    ];
    extraConfig = ''
      [XDebug]
      xdebug.mode = debug
      xdebug.start_with_request = yes
      xdebug.client_port = 9000
      xdebug.idekey = phpnixshell
      '';
  };
in
  pkgs.mkShell {
    buildInputs = [
      php
      php.packages.composer2
    ];

    shellHook = ''
      echo 
      export PATH=$PWD/vendor/bin:$PATH
    '';
  }
