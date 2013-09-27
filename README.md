# gitdo

integrate github issues with scrumdo


## setup

1. clone
2. run composer install
3. copy `config/parameters.yaml.dist` to `config/parameters.yaml` and edit it

```yaml
github:
    user:         your-github-user
    token:        your-github-api-token
    organization: your-organization
    project:      your-project

scrumdo:
    user:         your-scrumdo-user
    token:        your-scrumdo-password/token
    organization: your-organization
    project:      your-project
    iteration:    your-backlog-iteration-id
```

## run

`~$ ./bin/console gitdo:github-to-scrumdo`
