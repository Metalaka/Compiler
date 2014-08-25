<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2014, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Iterator
 */
-> import('Iterator.~');

}

namespace Hoa\Compiler\Llk\Sampler {

/**
 * Class \Hoa\Compiler\Llk\Sampler.
 *
 * Sampler parent.
 *
 * @author     Frédéric Dadeau <frederic.dadeau@femto-st.fr>
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2014 Frédéric Dadeau, Ivan Enderlin.
 * @license    New BSD License
 */

abstract class Sampler {

    /**
     * Compiler.
     *
     * @var \Hoa\Compiler\Llk\Parser object
     */
    protected $_compiler         = null;

    /**
     * Tokens.
     *
     * @var \Hoa\Compiler\Llk\Sampler array
     */
    protected $_tokens           = null;

    /**
     * All rules (from the compiler).
     *
     * @var \Hoa\Compiler\Llk\Sampler array
     */
    protected $_rules            = null;

    /**
     * Token sampler.
     *
     * @var \Hoa\Visitor\Visit object
     */
    protected $_tokenSampler     = null;

    /**
     * Root rule name.
     *
     * @var \Hoa\Compiler\Llk\Sampler string
     */
    protected $_rootRuleName     = null;

    /**
     * Current token namespace.
     *
     * @var \Hoa\Compiler\Llk\Sampler string
     */
    protected $_currentNamespace = 'default';

    /**
     * Skip token.
     *
     * @var \Hoa\Compiler\Llk\Sampler string
     */
    protected $_skipToken        = null;


    /**
     * Construct a generator.
     *
     * @access  public
     * @param   \Hoa\Compiler\Llk\Parser  $compiler        Compiler/parser.
     * @param   \Hoa\Visitor\Visit        $tokenSampler    Token sampler.
     * @return  void
     */
    public function __construct ( \Hoa\Compiler\Llk\Parser $compiler,
                                  \Hoa\Visitor\Visit       $tokenSampler ) {

        $this->_compiler     = $compiler;
        $this->_tokens       = $compiler->getTokens();
        $this->_rules        = $compiler->getRules();
        $this->_tokenSampler = $tokenSampler;
        $this->_rootRuleName = $compiler->getRootRule();
        $this->_skipToken    = $this->formatSkipToken();

        return;
    }

    /**
     * Format the skip token.
     *
     * @access  protected
     * @return  string
     */
    protected function formatSkipToken ( ) {

        $value = $this->_tokens['default']['skip'];

        if ('\\' == $value[0])
            switch ($value[1]) {
                case 'h': // any horizontal white space character
                    return chr(0x0009); // TAB

                case 's': // any white space character
                    /**
                     * @help http://pcre.org/pcre.txt
                     *
                     * The default \s characters are:
                     * HT    (9),
                     * LF    (10),
                     * VT    (11),
                     * FF    (12),
                     * CR    (13),
                     * space (32),
                     */
                    return chr(0x0020); // Space

                case 'c': // \cx       where x is any ASCII character
                    return substr($value, 1);

                case 'o': // \o{ddd..} character with octal code ddd..
                case '0': // \0dd      character with octal code 0dd
                    if ('{' == $this->_skipToken[2])
                        return \Hoa\String::fromCode(octdec(
                            substr($value, 3, -1)
                        ));
                    return \Hoa\String::fromCode(octdec(
                        substr($value, 2)
                    ));

                case 'x': // \x{hhh..} character with hex code hhh.. (non-JavaScript mode)
                          // \xhh      character with hex code hh
                case 'u': // \uhhhh    character with hex code hhhh (JavaScript mode only)
                    if ('{' == $this->_skipToken[2])
                        return \Hoa\String::fromCode(hexdec(
                            substr($value, 3, -1)
                        ));
                    return \Hoa\String::fromCode(hexdec(
                        substr($value, 2)
                    ));

                default:  // \ddd      character with octal code ddd
                    if (preg_match('/\\\\[0-7]{2,}/', $this->_skipToken))
                        return \Hoa\String::fromCode(octdec(
                            substr($value, 1)
                        ));
                    return stripcslashes($this->_skipToken);
            }

        return $value;
    }

    /**
     * Complete a token (namespace and representation).
     * It returns the next namespace.
     *
     * @access  protected
     * @param   \Hoa\Compiler\Llk\Rule\Token  $token    Token.
     * @return  string
     */
    protected function completeToken ( \Hoa\Compiler\Llk\Rule\Token $token ) {

        if(null !== $token->getRepresentation())
            return $this->_currentNamespace;

        $name = $token->getTokenName();
        $token->setNamespace($this->_currentNamespace);
        $toNamespace = $this->_currentNamespace;

        if(isset($this->_tokens[$this->_currentNamespace][$name])) {

            $token->setRepresentation(
                $this->_tokens[$this->_currentNamespace][$name]
            );
        }
        else {

            foreach($this->_tokens[$this->_currentNamespace] as $_name => $regex) {

                if(false === strpos($_name, ':'))
                    continue;

                list($_name, $toNamespace) = explode(':', $_name, 2);

                if($_name === $name)
                    break;
            }

            $token->setRepresentation($regex);
        }

        return $toNamespace;
    }

    /**
     * Set current token namespace.
     *
     * @access  protected
     * @param   string  $namespace    Token namespace.
     * @return  string
     */
    protected function setCurrentNamespace ( $namespace ) {

        $old                     = $this->_currentNamespace;
        $this->_currentNamespace = $namespace;

        return $old;
    }

    /**
     * Generate a token value.
     * Complete and set next token namespace.
     *
     * @access  protected
     * @param   \Hoa\Compiler\Llk\Rule\Token  $token    Token.
     * @return  string
     */
    protected function generateToken ( \Hoa\Compiler\Llk\Rule\Token $token ) {

        $toNamespace = $this->completeToken($token);
        $this->setCurrentNamespace($toNamespace);

        return $this->_tokenSampler->visit(
            $token->getAST()
        ) . $this->_skipToken;
    }
}

}

namespace {

/**
 * Flex entity.
 */
Hoa\Core\Consistency::flexEntity('Hoa\Compiler\Llk\Sampler\Sampler');

}
