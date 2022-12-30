<?php

namespace Handlebars;

/**
 * Encapsulates helpers arguments.
 *
 * @category Xamin
 * @package Handlebars
 * @author Dmitriy Simushev <simushevds@gmail.com>
 * @copyright 2014 Authors
 * @license MIT <http://opensource.org/licenses/MIT>
 * @version Release: @package_version@
 * @link http://xamin.ir
 */
class Arguments {

  /**
   * List of charcters that cannot be used in identifiers.
   */
  const NOT_VALID_NAME_CHARS = '!"#%&\'()*+,./;<=>@[\\]^`{|}~';

  /**
   * List of characters that cannot be used in identifiers in segment-literal
   * notation.
   */
  const NOT_VALID_SEGMENT_NAME_CHARS = "]";

  /**
   * List of named arguments.
   *
   * @var array
   */
  protected $namedArgs = [];

  /**
   * List of positional arguments.
   *
   * @var array
   */
  protected $positionalArgs = [];

  /**
   * The original arguments string that was used to fill in arguments.
   *
   * @var string
   */
  protected $originalString = '';

  /**
   * Class constructor.
   *
   * @param string $args_string
   *   Arguments string as passed to a helper.
   */
  public function __construct($args_string = FALSE) {
    $this->originalString = (string) $args_string;

    if ($this->originalString !== '') {
      $this->parse($args_string);
    }
  }

  /**
   * Returns string representation of the arguments list.
   *
   * This method is here mostly for backward compatibility reasons.
   *
   * @return string
   *   The arguments as string.
   */
  public function __toString() {
    return $this->originalString;
  }

  /**
   * Returns a list of named arguments.
   *
   * @return array
   *   The arguments.
   */
  public function getNamedArguments() {
    return $this->namedArgs;
  }

  /**
   * Returns a list of positional arguments.
   *
   * @return array
   *   The arguments.
   */
  public function getPositionalArguments() {
    return $this->positionalArgs;
  }

  /**
   * Breaks an argument string into arguments and stores them inside the object.
   *
   * @param string $args_string
   *   Arguments string as passed to a helper.
   *
   * @throws \InvalidArgumentException
   */
  protected function parse($args_string) {
    $bad_chars = preg_quote(self::NOT_VALID_NAME_CHARS, '#');
    $bad_seg_chars = preg_quote(self::NOT_VALID_SEGMENT_NAME_CHARS, '#');

    $name_chunk = '(?:[^' . $bad_chars . '\s]+)|(?:\[[^' . $bad_seg_chars . ']+\])';
    $variable_name = '(?:\.\.\/)*(?:(?:' . $name_chunk . ')[\.\/])*(?:' . $name_chunk . ')\.?';
    $special_variable_name = '@[a-z]+';
    $escaped_value = '(?:(?<!\\\\)".*?(?<!\\\\)"|(?<!\\\\)\'.*?(?<!\\\\)\')';
    $argument_name = $name_chunk;
    $argument_value = $variable_name . '|' . $escaped_value . '|' . $special_variable_name;
    $positional_argument = '#^(' . $argument_value . ')#';
    $named_argument = '#^(' . $argument_name . ')\s*=\s*(' . $argument_value . ')#';

    $current_str = trim($args_string);

    // Split arguments string.
    while (strlen($current_str) !== 0) {
      if (preg_match($named_argument, $current_str, $matches)) {
        // Named argument found.
        $name = $this->prepareArgumentName($matches[1]);
        $value = $this->prepareArgumentValue($matches[2]);

        $this->namedArgs[$name] = $value;

        // Remove found argument from arguments string.
        $current_str = ltrim(substr($current_str, strlen($matches[0])));
      }
      elseif (preg_match($positional_argument, $current_str, $matches)) {
        // A positional argument found. It cannot follow named arguments.
        if (count($this->namedArgs) !== 0) {
          throw new \InvalidArgumentException('Positional arguments cannot follow named arguments');
        }

        $this->positionalArgs[] = $this->prepareArgumentValue($matches[1]);

        // Remove found argument from arguments string.
        $current_str = ltrim(substr($current_str, strlen($matches[0])));
      }
      else {
        throw new \InvalidArgumentException(
          sprintf(
            'Malformed arguments string: "%s"',
            $args_string
          )
        );
      }
    }
  }

  /**
   * Prepares argument's value to add to arguments list.
   *
   * The method unescapes value and wrap it into \Handlebars\HandlebarsString
   * class if needed.
   *
   * @param string $value
   *   Argument's value.
   *
   * @return string|\Handlebars\HandlebarsString
   */
  protected function prepareArgumentValue(string $value) {
    // Check if argument's value is a quoted string literal.
    if ($value[0] == "'" || $value[0] == '"') {
      // Remove enclosing quotes and unescape.
      return new HandlebarsString(stripcslashes(substr($value, 1, -1)));
    }

    // Check if the value is an integer literal.
    if (preg_match("/^-?\d+$/", $value)) {
      // Wrap the value into the String class to tell the Context that
      // it's a value and not a variable name.
      return new HandlebarsString($value);
    }

    return $value;
  }

  /**
   * Prepares argument's name.
   *
   * Remove sections braces if needed.
   *
   * @param string $name
   *   Argument's name.
   *
   * @return string
   */
  protected function prepareArgumentName(string $name) {
    // Check if argument's name is a segment.
    if ($name[0] == '[') {
      $name = substr($name, 1, -1);
    }

    return $name;
  }
}
