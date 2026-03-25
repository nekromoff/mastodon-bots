# ActivityPub / Mastodon bots

A Mastodon (Activity Pub) bot instance developed in PHP to **quickly create and deploy multiple bots**. I use the bots to regularly post interesting content (historical photos).

Manual registration at large instances (like Mastodon.social) was slow: register -> verify -> create app -> add scopes... **This tool helps to deploy bots (new Mastodon accounts / identities) in couple of clicks.**

See it in action: https://bots.ambience.sk/

## Installation

Download the code or `git clone https://github.com/nekromoff/mastodon-bots`.

**Pure PHP**, no external dependencies.

**Apache server needed** because of **.htaccess rewrite rules**. But these could be easily converted to NGINX.

**SQLite** used as a database for low friction setup (database = single file).

See **Setup your first bot** below...

<img width="1182" height="460" alt="image" src="https://github.com/user-attachments/assets/e34a2773-a8e6-4298-90b3-589ce8bcc9a2" />

## Features

Multiuser (multibot) Fediverse instance. I recommend to host it on a domain or a subdomain. `index.php` is used to handle all traffic via rewrites.

### Public website

Public facing website with list of bots and their posts including their profiles and full Mastodon feeds.

Automatically generated based on the bots and their activity. Fully indexable by Google and other search engines. Verification via rel="me" included as well as bot creator META tag.

### Admin

- **Full administration panel** - create and manage your bots (multiple bots per instance)
- **Login:** single admin user - authenticated via password only
- **Dashboard** - quick overview of bot activity and their stats
- **Manage bots**
    - create
    - edit
    - post as a bot
    - social features - follow or block
    - move - migration from (_alsoKnownAs_) and to (_movedTo_) an instance
- **Logs** - log of all relevant events (incoming and outgoing) for all bots
- **Settings** - Mastodon instance setup, log retention, media limits

### Bot API

Very **simple API** to communicate with your bots.

#### Supported features
- Create a post
- Upload media
- Post with media (use ID from upload response)
- Edit a post (but you can also do that manually via Admin)
- Delete a post (but you can also do that manually via Admin)
- Follow a remote account

## Setup your first bot

1. Setup your instance by going to: example.com/admin/
2. Set domain/subdomain to be used for your ActivityPub / Mastodon instance.
3. Create your admin password
4. Login using your password.
5. **Create New Bot**, set username (no @ character), display name, short bio, password for the bot (keep it for later = API access)
6. **Edit** your bot to enter additonal details (extra fields, featured hashtags) and upload your profile icon (avatar) and a header image. Set options such as discoverability, featuring in Mastodon explore, search engine indexing, followers approval. All options preset for full visibility.
7. **Settings** and change log retention and media upload limits as needed.
8. See **API Usage** under settings for simple API communication with your bots.

## FAQ

### Can I run this on a shared hosting?
> Yes, no dependencies mean no need for `composer`. SQLite database is just a file in `data/` folder, so no need to set up a separate database.

### Can I run this on a subdomain?
> Yes, definitely. Just setup SSL certificate and upload this code and you are good to go.

### How quickly can I create a bot?
> Once you are done with a first time setup, you can create a bot in about 20 seconds. You will need some more time to edit bio, upload profile image etc.

### Can I run multiple bots on the same instance?
> Yes, I built it to do just right that!

### How do I post via API?
> API requests authenticate using bot username and password. You can use a custom script to post any content to Mastodon (ActivityPub). The API is very simple. 

> Example (creating a Mastodon post):
```
curl -u botname:botpassword \
     -X POST https://example.com/api/post \
     -H "Content-Type: application/json" \
     -d '{"content":"Hello Fediverse! #test","visibility":"public"}'
```

### Can I manually create posts?
> Yes, there is admin UI to manually create, edit and delete posts.

### Can I use this as an instance for users (e.g. not bots)?
> Yes, although I wouldn't recommend it. There is no way for users (other than admin) to log in. All users would share common admin interface with a single password.

Note: Computer-assisted process was used to develop this code.

## Please ⭐ star 🌟 this repo, if you like it and use it.
