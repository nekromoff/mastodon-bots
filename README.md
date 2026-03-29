# ActivityPub bots (compatible Mastodon bots)

A Mastodon compatible (Activity Pub) bot instance developed in PHP to **quickly create and deploy multiple bots**. I use the bots to regularly post interesting content (historical photos) to Mastodon.

Manual registration at large instances (like Mastodon.social) was slow: register -> verify -> create app -> add scopes... **This tool helps to deploy bots (new ActivityPub accounts / identities) in couple of clicks.**

See it in action: https://bots.ambience.sk/

## Technology

- **Pure PHP**, no external dependencies.
- **Apache server** needed because of **.htaccess rewrite rules**. But these could be easily converted to **NGINX**.
- **SQLite** used as a database for low friction setup (database = single file).

## Installation

1. Setup a domain/subdomain with SSL certificate, where your ActivityPub (Mastodon compatible) instance will run, e.g. `bots.example.com` or `example.com`.
2. Download the code or `git clone https://github.com/nekromoff/mastodon-bots` (to the root of domain/subdomain).
3. Continue to **Setup your first bot** below...

<img width="1365" height="747" alt="bot dashboard" src="https://github.com/user-attachments/assets/d090e4ad-d9af-4abd-bbcf-a96ad2f0a9d3" />

## Features

Multiuser (multibot) Fediverse instance. I recommend to host it on a domain or a subdomain. `index.php` is used to handle all traffic via rewrites.

### Public website

Public facing website with list of bots and their posts including their profiles and full Mastodon compatible feeds.

Automatically generated based on the bots and their activity. Fully indexable by Google and other search engines. Verification via rel="me" included as well as bot creator META tag.

### Admin

- **Full administration panel** - create and manage your bots (multiple bots per instance)
- **Login:** single admin user - authenticated via password only
- **Bots** - quick overview of bot activity and their stats
    - create a bot
    - edit a bot
    - post as a bot
    - social features - follow or block
    - move - migration features - move from (_alsoKnownAs_) or to (_movedTo_) an instance
- **Logs** - log of all relevant events (incoming and outgoing) for all bots
- **Settings** - instance setup, log retention, media limits

### Bot API

Very **simple API** to communicate with your bots.

#### Supported features
- Create a post
- Upload some media
- Post with media (use ID from upload response)
- Edit a post (but you can also do that manually via Admin)
- Delete a post (but you can also do that manually via Admin)
- Follow a remote account

## Setup your first bot

1. Open your instance at `example.com/admin/` and set it up including admin password.
2. Log in using your admin password.
3. **Create New Bot**, set username (no @ character), display name, short bio, password for the bot (keep it for later = API access)
4. **Edit** your bot to enter additonal details (extra fields, featured hashtags) and upload your profile icon (avatar) and a header image. Set options such as discoverability, featuring in Mastodon explore, search engine indexing, followers approval. All options preset for full visibility.
5. Go to **Settings** and change log retention and media upload limits as needed.
6. See **API Usage** under settings for simple API communication with your bots.

## FAQ

### Can I run this on a shared hosting?
> Yes, no dependencies mean no need for `composer`. SQLite database is just a file in `data/` folder, so no need to set up a separate database.

### Can I run this on a subdomain?
> Yes, definitely. Just setup an SSL certificate and upload this code and you are good to go.

### How quickly can I create a bot?
> Once you are done with a first time setup, you can create a bot in about 20 seconds. You will need some more time to edit bio, upload profile image etc.

![output](https://github.com/user-attachments/assets/ec158567-35bd-43f7-aa04-6335bfa1ebf6)

### Can I run multiple bots on the same instance?
> Yes, I built it to do just that!

### How do I post via API?
> API requests authenticate using bot username and password. You can use a custom script to post any content to Mastodon (ActivityPub). The API is very simple. 

> Example (creating a Mastodon / ActivityPub post):
```
curl -u botname:botpassword \
     -X POST https://example.com/api/post \
     -H "Content-Type: application/json" \
     -d '{"content":"Hello Fediverse! #test","visibility":"public"}'
```

### Can I manually create posts?
> Yes, there is an admin UI to manually create, edit and delete posts.

### Can I use this as an instance for users (e.g. not bots)?
> Yes, although I wouldn't recommend it. There is no way for users (other than admin) to log in. All users would share common admin interface with a single password.

Note: Computer-assisted process was used to develop this code.

## Please ⭐ star 🌟 this repo, if you like it and use it.
