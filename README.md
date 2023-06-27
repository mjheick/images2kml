# images2kml

Take some/many/ungodly quantity of images, extract time and gps data, and write kml to import into gps program such as google earth

# usage

Pick a ton of images and put them in the folder where the scripts lie.

```
find . -name '*.jpg' -exec php -f tag-image.php {} \;
```

This takes all your jpeg images and creates json metadata files that will be interpreted by the next process. Any images where there was an issue parsing either date/time or gps coords will sadly be left out.

Next, run:

```
php -f make-kml.php
```

This will not only reduce your images down to a maximum configured width of 500 pixels but will also create the necessary kml file.

You keep the kml file and all the images that were generated that also share the name of the kml file.

# work/*

As an example I've picked 5+ years of random personal images (scrubbed for clariy and not giving a crap about gps coordinates) and executed the scripts to show output.

Images are retained for data and fitness, and cause they're just images.

