# LinkSniffer Module
[![Build Status](https://scrutinizer-ci.com/g/WildPHP/module-uno/badges/build.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-uno/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/WildPHP/module-uno/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/WildPHP/module-uno/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/wildphp/module-uno/v/stable)](https://packagist.org/packages/wildphp/module-uno)
[![Latest Unstable Version](https://poser.pugx.org/wildphp/module-uno/v/unstable)](https://packagist.org/packages/wildphp/module-uno)
[![Total Downloads](https://poser.pugx.org/wildphp/module-uno/downloads)](https://packagist.org/packages/wildphp/module-uno)

This module shows information about a link when it is posted in a channel.

## System Requirements
If your setup can run the main bot, it can run this module as well.

## Installation
To install this module, we will use `composer`:

```composer require wildphp/module-uno```

That will install all required files for the module. In order to activate the module, add the following line to your modules array in `config.neon`:

    - WildPHP\Modules\Uno\Uno

The bot will run the module the next time it is started.

## Usage
For the rules of UNO, please refer to [the UNO rules](http://www.unorules.com/). This module adjusts the following rules:
* Only 1 card can be drawn per turn.
* Action cards do not take effect when they are the first card.
* The module yells UNO automatically.

Use `newgame` to start a game in a channel. You must have the `newgame` permission to start and stop games.

While running, the following commands are available:

* `play [card]`
    * Alias: `pl`
* `draw` - Draw a card if you do not have valid moves. Can only draw 1 card per turn.
    * Alias: `dr`
* `pass` - Pass the current turn if you do not have valid moves.
    * Alias: `pa`
* `validmoves` - Show available valid moves for the current top card.
    * Alias: `vm`
* `cards` - Show your cards.
* `stop` - Stop the current game.

It is possible to run multiple games at once, however you can only run 1 game per channel.


## License
This module is licensed under the GNU General Public License, version 3. Please see `LICENSE` to read it.
