#!/bin/bash

set -exu
__DIR__=$(
  cd "$(dirname "$0")"
  pwd
)
__PROJECT__=$(
  cd ${__DIR__}/../../../
  pwd
)
cd ${__DIR__}


{
  docker stop static-php-cli-dev-1
} || {
  echo $?
}
cd ${__DIR__}
IMAGE=alpine:3.16

cd ${__DIR__}
docker run --rm --name static-php-cli-dev-1 -d -v ${__PROJECT__}:/work -w /work $IMAGE tail -f /dev/null

