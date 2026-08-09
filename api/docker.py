import docker

client = docker.from_env()

containers = client.containers.list()

for container in containers:
    print(container.name)

    