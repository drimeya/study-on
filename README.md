## StudyOn — быстрый старт

Этот репозиторий — основной сервис **StudyOn** (клиентская часть). Отдельный проект **StudyOn.Billing** расположен в соседней папке `../study-on.bulling` и используется как сервис биллинга по HTTP.

### 1. Быстрый запуск окружения

Требования:
- Docker + Docker Compose
- Make

Запускать **из корня монорепозитория `stu`**:

```bash
cd /Users/polina/Documents/stu

# 1. Создать общую сеть (если ещё не создана)
docker network create study-on-network || true

# 2. Поднять биллинг
cd study-on.bulling
make full-up

# 3. Поднять основной сервис StudyOn
cd ../study-on
make full-up
```

Что делает `make full-up`:
- `docker-compose up -d`
- `php bin/console doctrine:migrations:migrate`
- `php bin/console doctrine:fixtures:load`

### 2. URLs для проверки в браузере

- Основной клиент StudyOn:  
  `http://localhost:85/`  
  (редиректит на список курсов `/courses`).

- Биллинг (API, Swagger UI):  
  `http://localhost:86/api/v1/doc`

### 3. Пользователи и доступы

#### Биллинг (StudyOn.Billing)

Фикстуры биллинга создают базовых пользователей:

- Обычный пользователь:
  - Email / username: `user@intaro.ru`
  - Пароль: `password123`

- Администратор биллинга:
  - Email / username: `admin@intaro.ru`
  - Пароль: `adminpassword`

Через API (например, из Swagger UI `http://localhost:86/api/v1/doc`) можно:
- зарегистрировать нового пользователя `POST /api/v1/register`
- авторизоваться `POST /api/v1/auth`
- получить текущего пользователя `GET /api/v1/users/current` c JWT.

#### Клиент StudyOn

В функциональных тестах и при реальном запуске фронт ходит в биллинг и использует тех же пользователей. Для ручной проверки можно:

- Зайти под **обычным пользователем**:
  - Email: `user@intaro.ru`
  - Пароль: `password123`

- Зайти под **админом** (ROLE\_SUPER\_ADMIN):
  - Email: `admin@intaro.ru`
  - Пароль: `adminpassword`

После входа:
- список курсов (`/courses`) доступен всем, даже без авторизации;
- просмотр курса (`/courses/{code}`) — тоже доступен всем;
- просмотр урока (`/lessons/{id}`) и профиль (`/profile`) — **только авторизованным пользователям**;
- создание/редактирование/удаление курса и уроков — **только администратору**.

### 4. Запуск автотестов

Все тесты уже настроены и используют тестовую БД и моки биллинга.

Из корня проекта `study-on`:

```bash
cd /Users/polina/Documents/stu/study-on

# Полный прогон тестов (создаст тестовую БД, накатает миграции и запустит phpunit)
make test
```

Что делает `make test`:
- `php bin/console doctrine:database:create --env=test || true`
- `php bin/console doctrine:migrations:migrate --env=test --no-interaction`
- `php bin/phpunit`

Набор тестов:
- авторизация и доступ (`AuthTest`, `AccessControlTest`)
- регистрация (`RegistrationTest`)
- контроллеры курсов и уроков (`CourseControllerTest`, `LessonControllerTest`)

### 5. Кратко о структуре

- Основной сервис: `study-on`
- Биллинг‑сервис: `study-on.bulling`
- Маршруты Symfony:
  - `/login`, `/register` — анонимный доступ
  - `/courses`, `/courses/{code}` — анонимный доступ к курсам
  - `/lessons/{id}`, `/profile` и прочее — только авторизованные пользователи
