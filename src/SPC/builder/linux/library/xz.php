<?php
/**
 * Copyright (c) 2022 Yun Dou <dixyes@gmail.com>
 *
 * lwmbs is licensed under Mulan PSL v2. You can use this
 * software according to the terms and conditions of the
 * Mulan PSL v2. You may obtain a copy of Mulan PSL v2 at:
 *
 * http://license.coscl.org.cn/MulanPSL2
 *
 * THIS SOFTWARE IS PROVIDED ON AN "AS IS" BASIS,
 * WITHOUT WARRANTIES OF ANY KIND, EITHER EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO NON-INFRINGEMENT,
 * MERCHANTABILITY OR FIT FOR A PARTICULAR PURPOSE.
 *
 * See the Mulan PSL v2 for more details.
 */

declare(strict_types=1);

namespace SPC\builder\linux\library;

use SPC\exception\RuntimeException;

class xz extends LinuxLibraryBase
{
    public const NAME = 'xz';

    /**
     * @throws RuntimeException
     */
    public function build()
    {
        [,,$destdir] = SEPARATED_PATH;

        shell()->cd($this->source_dir)
            // ->exec('autoreconf -i --force')
            ->exec(
                "{$this->builder->configure_env} ./configure " .
                '--enable-static ' .
                '--disable-shared ' .
                "--host={$this->builder->gnu_arch}-unknown-linux " .
                '--disable-xz ' .
                '--disable-xzdec ' .
                '--disable-lzmadec ' .
                '--disable-lzmainfo ' .
                '--disable-scripts ' .
                '--disable-doc ' .
                '--with-libiconv ' .
                '--prefix='
            )
            ->exec('make clean')
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
    }
}
