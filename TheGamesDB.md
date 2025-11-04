# TheGamesDB.net Website and API

This document provides an overview of the TheGamesDB.net website and API source code.

## Project Overview

TheGamesDB.net is a website that serves as a frontend to a complete database of video games. The site includes artwork and metadata that can be incorporated into various HTPC software and plug-ins via the API.

The project is built using PHP and MySQL. A Docker-based development environment is provided for easy setup.

## Root Directory Structure

The root directory contains the following files and folders:

*   **`.git/`**: Contains the Git version control history.
*   **`.github/`**: Contains GitHub-specific files, such as issue templates.
*   **`apache_config/`**: Contains Apache web server configuration files.
*   **`API/`**: Contains the source code for the TheGamesDB.net API.
*   **`cdn/`**: This directory is likely used for content delivery network (CDN) purposes, for serving static assets like images. The README mentions that `createimage.php` should be copied here.
*   **`db/`**: Contains database-related files, including the initial database schema and migrations.
*   **`docker/`**: Contains files for the Docker development environment.
*   **`include/`**: Contains common PHP classes and configuration files used by both the website and the API.
*   **`tools/`**: Contains various command-line tools for managing the database and other tasks.
*   **`vendor/`**: Contains third-party libraries managed by Composer.
*   **`website/`**: Contains the source code for the TheGamesDB.net website frontend.
*   **`.gitignore`**: Specifies files and directories that should be ignored by Git.
*   **`composer.json`**: Defines the project's PHP dependencies for Composer.
*   **`composer.lock`**: Records the exact versions of the dependencies that are installed.
*   **`docker-compose.yml`**: The Docker Compose file for setting up the development environment.
*   **`LICENSE`**: The license for the project.
*   **`README.md`**: The main README file for the project, containing setup instructions and other information.

---

## .github Directory

This directory contains GitHub-specific files.

*   **`ISSUE_TEMPLATE/`**: Contains templates for creating new issues on GitHub.
    *   **`bug_report.md`**: The template for reporting a bug.
    *   **`feature_request.md`**: The template for requesting a new feature.

---

## apache_config Directory

This directory contains Apache web server configuration files.

*   **`000-default.conf`**: This file contains a rule to redirect all HTTP traffic to HTTPS.
*   **`api.conf`**: This file contains a rule to redirect all requests to the API to `index.php`. This is a common pattern for front controller-based applications.
*   **`cdn.conf`**: This file contains a rule to redirect requests for non-existing images to `createimage.php`. This script likely generates the images on the fly.

---

## API Directory

This directory contains the source code for the TheGamesDB.net API. The API is built using the Slim framework and provides endpoints for accessing the game database.

*   **`index.php`**: The entry point for the API. It initializes the Slim application, loads dependencies, middleware, and routes.
*   **`spec.yaml`**: The OpenAPI (Swagger) specification for the API. It defines all the available endpoints, their parameters, and responses.
*   **`spec.dev.yaml`**: A development version of the API specification.
*   **`swagger-ui*/`**: Files related to Swagger UI, which provides a web-based interface for exploring and testing the API.
*   **`key.php`**: This file probably contains logic for handling API keys.
*   **`oauth2-redirect.html`**: A file for handling OAuth2 redirects, likely used with the Swagger UI.
*   **`favicon-16x16.png`, `favicon-32x32.png`**: Favicon files for the API documentation page.

### `include/` Directory

This directory contains the core logic of the API.

*   **`routes.php`**: Defines all the API routes and their handlers. The routes are grouped into `/v1` and `/v1.1`.
*   **`dependencies.php`**: Sets up the dependencies for the application, such as the database connection and logger.
*   **`middleware.php`**: Registers the middleware for the application, including an authentication middleware.
*   **`settings.php`**: Contains the configuration settings for the application.
*   **`APIAccessDB.class.php`**: This class handles database interactions related to API access, such as retrieving user allowance, counting API requests, and generating API keys.
*   **`Utils.class.php`**: This class provides several utility functions for the API, such as handling status messages, pagination, parsing request options, and validating input.

### `templates/` Directory

This directory contains templates for the API documentation page.

*   **`doc.html`**: The main template for the API documentation.
*   **`doc.dev.html`**: The development version of the API documentation template.

---

## db Directory

This directory contains database-related files, including the initial database schema and migrations.

*   **`init.sql`**: This file contains the initial database schema for the project. It defines the following tables:
    *   `ESRB_rating`: Stores ESRB ratings.
    *   `api_allowance_level`: Stores API allowance levels.
    *   `api_month_counter`: Stores API usage counts.
    *   `apiusers`: Stores information about API users and their keys.
    *   `banners`: Stores information about banners (images).
    *   `comments`: Stores comments on games.
    *   `countries`: Stores a list of countries.
    *   `devs_list`: Stores a list of developers.
    *   `games`: The main table for games, containing information such as title, release date, overview, etc.
    *   `games_alts`: Stores alternative game titles.
    *   `games_devs`: A mapping table between games and developers.
    *   `games_genre`: A mapping table between games and genres.
    *   `games_hashes`: Stores game hashes.
    *   `games_legacy`: A legacy table for games.
    *   `games_lock`: Stores information about locked games.
    *   `games_pubs`: A mapping table between games and publishers.
    *   `games_reports`: Stores user-submitted reports for games.
    *   `games_uids`: Stores unique identifiers for games.
    *   `games_uids_patterns`: Stores patterns for unique identifiers.
    *   `genres`: Stores a list of genres.
    *   `platforms`: Stores information about platforms.
    *   `platforms_images`: Stores images for platforms.
    *   `pubdev`: A table related to publishers and developers.
    *   `publishers`: Stores a list of publishers.
    *   `pubs_list`: A list of publishers.
    *   `ratings`: Stores user ratings for items.
    *   `regions`: Stores a list of regions.
    *   `statistics`: Stores various statistics.
    *   `user_edits`: Stores user edits to games.
    *   `user_games`: A mapping table between users and their games.
    *   `users`: The main table for users.
*   **`migrations/`**: This directory contains SQL files for database migrations.
    *   **`20230518_add_new_dreamcast_uid_patterns.sql`**: A migration to add new Dreamcast UID patterns.

---

## docker Directory

This directory contains files for the Docker development environment.

*   **`apache/`**: This directory contains the Apache configuration for the Docker environment.
    *   **`api.conf`**: This file contains a rule to redirect all requests to the API to `index.php`.

---

## include Directory

This directory contains common PHP classes and configuration files used by both the website and the API.

*   **`CommonUtils.class.php`**: Provides common utility functions, such as getting the base URLs for the website, API, and box art, and a function for recursively decoding HTML special characters in an array.
*   **`config.class.example.php`**: An example configuration file. It should be renamed to `config.class.php` and contains settings for Discord webhooks, Cloudflare credentials, and a debug flag.
*   **`db.config.template.php`**: A template for the database configuration file. It should be renamed to `db.config.php` and contains the database connection details (DSN, username, password). It uses a singleton pattern to provide a single database connection instance.
*   **`GameLock.class.php`**: This class is used to handle locking of game data to prevent concurrent editing. It interacts with the `games_lock` table in the database.
*   **`TGDB.API.php`**: This file contains the core API logic for interacting with the database. It has methods for fetching games, platforms, developers, publishers, genres, etc. It also has methods for searching and filtering data.

---

## tools Directory

This directory contains various command-line tools for managing the database and other tasks.

*   **`createimage.php`**: This script is used to create resized versions of images. It takes an image path and a size as input, and generates a new image with the specified dimensions. It uses the `claviska/simpleimage` library for image manipulation. This script is likely used in conjunction with the `cdn.conf` Apache configuration to generate images on the fly.
*   **`fix_image_paths.db.php`**: This script is a database utility to fix image paths in the `banners` table. It removes the "original/" prefix from the `filename` column.

---

## website Directory

This directory contains the source code for the TheGamesDB.net website frontend. It is built with PHP and uses a traditional file-based approach for routing, where each PHP file represents a different page.

### Root Files

*   **`index.php`**: The home page of the website.
*   **`game.php`**: Displays the details of a specific game.
*   **`platform.php`**: Displays the details of a specific platform.
*   **`browse.php`**: Allows users to browse games.
*   **`search.php`**: The search page.
*   **`login.php`**: The login page.
*   **`register_new_user.php`**: The user registration page.
*   **`add_game.php`**: A page for adding new games.
*   **`edit_game.php`**: A page for editing existing games.
*   **`add_platform.php`**: A page for adding new platforms.
*   **`edit_platform.php`**: A page for editing existing platforms.
*   **`list_devs.php`**: A page for listing all developers.
*   **`list_pubs.php`**: A page for listing all publishers.
*   **`list_platforms.php`**: A page for listing all platforms.
*   **`list_games.php`**: A page for listing all games.
*   **`my_games.php`**: A page for users to view their own game collection.
*   **`my_games_by_platform.php`**: A page for users to view their game collection sorted by platform.
*   **`stats.php`**: Displays statistics about the database.
*   **`user_contrib.php`**: Displays user contributions.
*   **`contr.php`**: This is likely related to user contributions.
*   **`change_password.php`**: The page for users to change their password.
*   **`merge_dev_pub.php`**: A page for merging duplicate developers and publishers.
*   **`missing.php`**: A page for displaying missing information in the database.
*   **`report_review.php`**: A page for reporting reviews.

---

### actions/ Directory

This directory contains PHP scripts that handle form submissions and other actions from the website frontend.

*   **`add_dev_pub.php`**: Handles the submission of the form for adding a new developer or publisher.
*   **`add_game_bookmark.php`**: Handles the action of bookmarking a game.
*   **`add_game.php`**: Handles the submission of the form for adding a new game.
*   **`add_platform.php`**: Handles the submission of the form for adding a new platform.
*   **`delete_art.php`**: Handles the action of deleting an artwork.
*   **`delete_game.php`**: Handles the action of deleting a game.
*   **`edit_game.php`**: Handles the submission of the form for editing a game.
*   **`edit_platform.php`**: Handles the submission of the form for editing a platform.
*   **`game_search_count.php`**: This script likely increments the search count for a game.
*   **`merge_dev_pub.php`**: Handles the submission of the form for merging developers and publishers.
*   **`report_game.php`**: Handles the submission of the form for reporting a game.
*   **`resolve_game_report.php`**: Handles the action of resolving a game report.
*   **`uploads.php`**: Handles file uploads, likely for game artwork.

---

### css/ Directory

This directory contains the CSS files for the website.

*   **`bootstrap.min.css`**: The core Bootstrap CSS file.
*   **`darkly-bootstrap.min.css`, `litera-bootstrap.min.css`, `lux-bootstrap.min.css`, `materia-bootstrap.min.css`, `minty-bootstrap.min.css`, `superhero-bootstrap.min.css`**: Various Bootstrap themes.
*   **`fontawesome.5.0.10.css`, `fa-brands.5.0.10.css`**: Font Awesome CSS files for icons.
*   **`jquery.fancybox.min.3.3.5.css`**: The CSS for the Fancybox jQuery plugin, used for image lightboxes.
*   **`select-pure.css`**: The CSS for the Select-Pure JavaScript library, used for creating custom select boxes.
*   **`social-btn.css`**: CSS for social media buttons.
*   **`main.css`**: The main custom stylesheet for the website.
*   **`fine_uploader.5.16.2/`**: Contains the CSS files for the Fine Uploader library, used for file uploads.
*   **`webfonts/`**: Contains the webfont files for Font Awesome.

---

### images/ Directory

This directory contains images used in the website's frontend.

*   **`close.png`, `next.png`, `prev.png`**: Images for UI elements, such as carousels or lightboxes.
*   **`loading.gif`**: An animated GIF used to indicate loading.
*   **`if_recent-time-search-reload-time_2075824.svg`**: An SVG icon, likely for a "recent" or "reload" button.
*   **`ribbon.xcf`**: An XCF file, which is the native format for the GIMP image editor. This is likely the source file for the ribbon images.
*   **`ribbonBanners.png`, `ribbonClearlogos.png`, `ribbonFanarts.png`, `ribbonScreens.png`, `ribbonTitlescreens.png`**: PNG images of ribbons, likely used to overlay on top of game artwork to indicate something, for example, the type of the image.

---

### include/ Directory

This directory contains various helper classes and utilities for the website.

*   **`DiscordUtils.class.php`**: A class for sending notifications to Discord. It is used to post updates about new, updated, or removed games and images.
*   **`ErrorPage.class.php`**: A class for displaying error pages.
*   **`header.footer.class.php`**: Contains the `HEADER` and `FOOTER` classes, which are used to generate the common header and footer for the website.
*   **`login.common.class.php`**: This file includes the appropriate login class based on the debug configuration.
*   **`login.phpbb.class.php`**: A class for handling user authentication using a phpBB forum database.
*   **`login.pseudo.class.php`**: A pseudo login class for development purposes.
*   **`login.tgdb.class.php`**: A class for handling user authentication against the TheGamesDB database.
*   **`PaginationUtils.class.php`**: A utility class for creating pagination controls.
*   **`TGDBUtils.class.php`**: A utility class with functions for getting cover images and placeholder images.
*   **`UploadHandler.fineupload.class.php`**: A class for handling file uploads, based on the Fine Uploader library.
*   **`WebUtils.class.php`**: A utility class with functions for truncating text and purging the Cloudflare CDN cache.
*   **`sql/`**: This directory is empty.

---

### js/ Directory

This directory contains the JavaScript files for the website.

*   **`jquery-3.2.1.min.js`**: The jQuery library.
*   **`popper.min.1.13.0.js`**: The Popper.js library, used by Bootstrap for tooltips and popovers.
*   **`bootstrap.min.4.0.0.js`**: The Bootstrap JavaScript library.
*   **`fontawesome.5.0.10.js`, `brands.5.0.10.js`**: The Font Awesome JavaScript library for icons.
*   **`Chart.2.7.2.js`**: The Chart.js library, used for creating charts and graphs.
*   **`jquery.fancybox.3.3.5.js`**: The Fancybox jQuery plugin, used for image lightboxes.
*   **`fancybox.config.js`**: Configuration file for the Fancybox plugin.
*   **`pure-select.modded.0.6.2.js`**: A modified version of the Pure-Select library, used for creating custom select boxes.
*   **`fine_uploader.5.16.2/`**: Contains the JavaScript files for the Fine Uploader library, used for file uploads.

---
