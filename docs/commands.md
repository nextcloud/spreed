# Chat commands

!!! note

    For security reasons commands can only be added via the
    command line. `./occ  talk:command:add --help` gives you
    a short overview of the required arguments, but they are
    explained here in more depth.

---

## "Add command" arguments

| Argument   | Allowed chars | Description                                                                                                                                                                                                                                                                       |
|------------|---------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `cmd`      | [a-z0-9]      | The keyword the user has to type to run this command (min. 1, max. 64 characters)                                                                                                                                                                                                 |
| `name`     | *             | The author name of the response that is posted by the command (min. 1, max. 64 characters)                                                                                                                                                                                        |
| `script`   | *             | Actual command that is being ran. The script must be executable by the user of your webserver and has to use absolute paths only! See the parameter table below for options. The script is invoked with `--help` as argument on set up, to check if it can be executed correctly. |
| `response` | 0-2           | Who should see the response: 0 - No one, 1 - User who executed the command, 2 - Everyone                                                                                                                                                                                          |
| `enabled`  | 0-3           | Who can use the command: 0 - No one, 1 - Moderators of the room, 2 - Logged in users, 3 - Everyone                                                                                                                                                                                |

## Script parameter

| Parameter     | Description                                        |
|---------------|----------------------------------------------------|
| `{ROOM}`      | The token of the room the command was used in      |
| `{USER}`      | ID of the user that called the command             |
| `{ARGUMENTS}` | Everything the user write after the actual command |

## Example

### Create `/path/to/calc.sh`

```
    while test $# -gt 0; do
      case "$1" in
        --help)
          echo "/calc - A Nextcloud Talk chat wrapper for gnome-calculator"
          echo " "
          echo "Simple equations: /calc 3 + 4 * 5"
          echo "Complex equations: /calc sin(3) + 3^3 * sqrt(5)"
          exit 0
          ;;
        *)
          break
          ;;
     esac
    done

    set -f
    echo "$@ ="
    echo $(gnome-calculator --solve="$@")
```
    
Please note, that your command should also understand the argument `--help`.
It should return a useful description, the first line is also displayed in a list of all commands when the user just types `/help`.

### Register command


Make sure to use the absolute path to your script when registering the command:

```
./occ talk:command:add calculator calculator "/path/to/calc.sh {ARGUMENTS} {ROOM} {USER}" 1 3
```

### Explanation
* User input by user `my user id` in the chat of room `index.php/call/4tf349j`:
    
    ```
    /calculator 1 + 2 + 3 + "hello"
    ```

    
* Executed shell command:

    ```
    /path/to/calc.sh '1 + 2 + 3 + "hello"' '4tf349j' 'my user id'
    ```

## Aliases

It is also possible to define an alias for a command. This allows e.g. to get the `/help` command also with the german word `/hilfe`.

An alias for the `/calculator` command from above could be created using the following command:

```
./occ talk:command:add calc calculator "alias:calculator" 1 3
```

Now `/calculator 1 + 2 + 3` and `/calc 1 + 2 + 3` result in the same message.


!!! note

    The enabled and response flag of the alias are ignored and the flags of the original command will be used and respected.
