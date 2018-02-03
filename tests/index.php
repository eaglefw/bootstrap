<?php

/**
 * MIT License
 * Copyright (c) 2018 EagleFw
 * http://www.eaglefw.com/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Eagle\Bootstrap;
use Eagle\Debugger;

require_once '../vendor/autoload.php';
require_once '../../debugger/vendor/autoload.php';

define('__APP__' , realpath(__DIR__ . '/app'));

/**
 * Initializing bootstrap
 * ----------------------
 */

Debugger::setMode(Debugger::ON);

$bootstrap = new Bootstrap();

$bootstrap->setAppDirectory(__APP__);

$bootstrap->addConfiguration(__APP__ . '/config/config.jso', Bootstrap::CONFIG_TYPE_JSON);

$bootstrap->createAutoloader()
          ->registerDirs([
	          __APP__ . '/library'
          ]);

/**
 * Running application
 */


$dependencyContainer = $bootstrap->getDependencyContainer();

