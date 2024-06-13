# drucheck
Drupal check - this is a docker container that helps you quickly check the code for compliance with Drupal standards

Build
--------------------

Build from `Dockerfile`:

    ``` sh
    $ docker build -t neomen/drucheck:latest .
    ```

Verify build:

    ``` sh
    $ sudo docker run --rm -it neomen/drucheck:latest --version
    ```

Usage
--------------------

1. Install the `neomen/drucheck:latest` container (optional - this step is performed by Docker automatically when running the container):

    ``` sh
    $ docker pull neomen/drucheck:latest
    ```

2. Define an bash alias that runs this container whenever `drucheck` is invoked on the command line:

	``` sh
	$ echo "alias drucheck='docker run --rm -it -v \$(pwd):/workspace neomen/drucheck:latest'" >> ~/.bashrc
	$ source ~/.bashrc
	```

3. Run drucheck as always:

	``` sh
	$ drucheck --version
	```