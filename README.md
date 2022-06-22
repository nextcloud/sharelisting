# ShareListing

This app allows generating reports of shares on the system.


## Usage

Command:
./occ sharing:list

Options:
* --user[=USER] : Will list shares of the given user
* --path[=PATH] : Will only consider the given path
* --filter[=FILTER] : Filter shares, possible values: owner, initiator, recipient

## Examples

To better illustrate how the app work see the examples below:


### Example 1 

Listing all shares user0 is a participant in (be it owner, initiator or recipient):

`./occ sharing:list --user user0`

```json
[
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:26+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user1"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:34:58+00:00",
        "permissions": 31,
        "path": "\/F2",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:35:02+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:43+00:00",
        "permissions": 1,
        "path": "\/F1\/SF1",
        "type": "link",
        "token": "eoT8kF5B9jtmMda"
    }
]
```

### Example 2

Listing all shares user0 is a participant in (be it owner, initiator or recipient) limited to the path `F1`

`./occ sharing:list --user user0 --path F1`

```json
[
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:26+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user1"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:35:02+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:43+00:00",
        "permissions": 1,
        "path": "\/F1\/SF1",
        "type": "link",
        "token": "eoT8kF5B9jtmMda"
    }
]
```

### Example 3:
List all info about all shares

`./occ sharing:list`

```json
[
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:34:58+00:00",
        "permissions": 31,
        "path": "\/F2",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:35:02+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:26+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user1"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:43+00:00",
        "permissions": 1,
        "path": "\/F1\/SF1",
        "type": "link",
        "token": "eoT8kF5B9jtmMda"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:26+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user1"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:34:58+00:00",
        "permissions": 31,
        "path": "\/F2",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "admin",
        "time": "2018-04-24T07:35:02+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user0"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:43+00:00",
        "permissions": 1,
        "path": "\/F1\/SF1",
        "type": "link",
        "token": "eoT8kF5B9jtmMda"
    }
]
```

#### Example 4

List all shares that user0 is the initiator of in the path F1 of user0.

`./occ sharing:list --user user0 --path F1 --filter initiator`

```json
[
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:26+00:00",
        "permissions": 31,
        "path": "\/F1",
        "type": "user",
        "recipient": "user1"
    },
    {
        "owner": "admin",
        "initiator": "user0",
        "time": "2018-04-24T08:29:43+00:00",
        "permissions": 1,
        "path": "\/F1\/SF1",
        "type": "link",
        "token": "eoT8kF5B9jtmMda"
    }
]
```
