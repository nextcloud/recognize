OC.L10N.register(
    "recognize",
    {
    "Recognize" : "Распознавание",
    "Smart media tagging and face recognition with on-premises machine learning models" : "Интеллектуальная маркировка медиаконтента и распознавание лиц с использованием локальных моделей машинного обучения",
    "Your server does not support AVX instructions" : "Ваш сервер не поддерживает набор инструкций AVX.",
    "Your server does not have an x86 64-bit CPU" : "На сервере установлен процессор, отличный от x86 64-бита",
    "Your server uses musl libc" : "На сервере используется библиотека musl libc",
    "Failed to load settings" : "Не удалось загрузить параметры",
    "Failed to save settings" : "Не удалось сохранить параметры",
    "never" : "никогда",
    "{time} ago" : "{time} назад",
    "Status" : "Состояние",
    "The machine learning models have been downloaded successfully." : "Модели машинного обучения загружены успешно.",
    "The machine learning models still need to be downloaded." : "Необходима загрузка моделей машинного обучения.",
    "Could not execute the Node.js binary. You may need to set the path to a working binary manually." : "Не удалось запустить выполнение двоичного файла Node.js. Попробуйте указать путь до бинарного файла вручную.",
    "Background Jobs are not executed via cron. Recognize requires background jobs to be executed via cron." : "Фоновые задания не выполняются через cron. Распознавание требует выполнения фоновых заданий через cron.",
    "The app is installed and will automatically classify files in background processes." : "Приложение установлено и выполняет распознавание файлов в фоновом режиме.",
    "None of the tagging options below are currently selected. The app will currently do nothing." : "Не выбран ни один режим назначения меток, выполнение остановлено.",
    "Face recognition" : "Распознавание лиц",
    "Face recognition is working. " : "Процесс распознавания лиц выполняется.",
    "An error occurred during face recognition, please check the Nextcloud logs." : "При распознавании лиц произошла ошибка, более подробные сведения приведены в файлах журнала Nextcloud.",
    "Waiting for status reports on face recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Ожидание событий от модуля распознавания лиц. Если это сообщение выводится более 15 минут, обратитесь к файлам журнала Nextcloud.",
    "Face recognition:" : "Распознавание лиц:",
    "Queued files" : "файлов в очереди",
    "Last classification: " : "последнее определение:",
    "Scheduled background jobs: " : "Запланированные фоновые задания:",
    "Last background job execution: " : "Последнее выполнение фонового задания: ",
    "There are queued files in the face recognition queue but no background job is scheduled to process them." : "В очереди распознавания лиц есть файлы, но фоновое задание для их обработки не запланировано.",
    "Face clustering:" : "Кластеризация лиц:",
    "faces left to cluster" : "лиц осталось сгруппировать",
    "Last clustering run: " : "Последний запуск кластеризации:",
    "A minimum of 120 faces per user is necessary for clustering to kick in" : "Для запуска кластеризации необходимо минимум 120 лиц на каждого пользователя",
    "Enable face recognition (groups photos by faces that appear in them; UI is in the photos app)" : "Включить распознавание лиц (группирует фотографии по лицам, которые на них изображены; пользовательский интерфейс находится в приложении «Фотографии»)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~500 or more, in WASM mode ~50 is recommended)" : "Количество файлов, обрабатываемых в одном процессе. Процессы будут запускаться каждые пять минут. Рекомендуемое значение в обычном режиме: 500 и более, в режиме WASM: 50.",
    "Object detection & landmark recognition" : "Обнаружение объектов и распознавание ориентиров",
    "Object recognition is working." : "Процесс распознавания объектов выполняется.",
    "An error occurred during object recognition, please check the Nextcloud logs." : "При распознавании объектов произошла ошибка, более подробные сведения приведены в файлах журнала Nextcloud.",
    "Waiting for status reports on object recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Ожидание событий от модуля распознавания объектов. Если это сообщение выводится более 15 минут, обратитесь к файлам журнала Nextcloud.",
    "Object recognition:" : "Распознавание объектов:",
    "There are queued files in the object detection queue but no background job is scheduled to process them." : "В очереди обнаружения объектов есть файлы, но фоновое задание для их обработки не запланировано.",
    "Landmark recognition is working." : "Распознавание ориентиров работает.",
    "An error occurred during landmark recognition, please check the Nextcloud logs." : "Произошла ошибка при распознавании ориентира. Проверьте журналы Nextcloud.",
    "Waiting for status reports on landmark recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Ожидаем отчетов о состоянии распознавания ориентиров. Если это сообщение сохраняется более 15 минут, проверьте журналы Nextcloud.",
    "Landmark recognition:" : "Знаковое признание:",
    "There are queued files in the landmarks queue but no background job is scheduled to process them." : "В очереди ориентиров есть файлы, но фоновое задание для их обработки не запланировано.",
    "Enable object recognition (e.g. food, vehicles, landscapes)" : "Включить распознавание объектов (еда, транспорт, ландшафты и прочее)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~100 or more, in WASM mode ~20 is recommended)" : "Количество файлов, обрабатываемых в одном процессе. Процессы будут запускаться каждые пять минут. Рекомендуемое значение в обычном режиме: 100 и более, в режиме WASM: 20.",
    "Enable landmark recognition (e.g. Eiffel Tower, Golden Gate Bridge)" : "Включить распознавание достопримечательностей (Эйфелева башня, мост Золотые ворота) ",
    "Audio tagging" : "Назначение меток аудиозаписям",
    "Audio recognition is working." : "Процесс распознавания аудиозаписей выполняется.",
    "An error occurred during audio recognition, please check the Nextcloud logs." : "При распознавании музыки произошла ошибка, более подробные сведения приведены в файлах журнала Nextcloud.",
    "Waiting for status reports on audio recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Ожидание событий от модуля распознавания музыки. Если это сообщение выводится более 15 минут, обратитесь к файлам журнала Nextcloud.",
    "Music genre recognition:" : "Распознавание музыкальных жанров:",
    "There are queued files but no background job is scheduled to process them." : "Файлы поставлены в очередь, но фоновое задание для их обработки не запланировано.",
    "Enable music genre recognition (e.g. pop, rock, folk, metal, new age)" : "Включить распознавание жанров музыки (поп, рок, фольк, метал, нью-эйдж)",
    "Video tagging" : "Пометка видеозаписей",
    "Video recognition is working." : "Процесс распознавания видеофайлов выполняется.",
    "An error occurred during video recognition, please check the Nextcloud logs." : "При распознавании видео произошла ошибка, более подробные сведения приведены в файлах журнала Nextcloud.",
    "Waiting for status reports on video recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Ожидание событий от модуля распознавания видео. Если это сообщение выводится более 15 минут, обратитесь к файлам журнала Nextcloud.",
    "Video recognition:" : "Распознавание видеофайлов:",
    "Enable human action recognition (e.g. arm wrestling, dribbling basketball, hula hooping)" : "Включить распознавание действий людей (армреслинг, ведение меча в баскетболе, вращение обруча)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~20 or more, in WASM mode ~5 is recommended)" : "Количество файлов, обрабатываемых в одном процессе. Процессы будут запускаться каждые пять минут. Рекомендуемое значение в обычном режиме: 20 и более, в режиме WASM: 5.",
    "Reset" : "Сброс",
    "Click the button below to remove all tags from all files that have been classified so far." : "Для очистки всех присвоенных меток, нажмите кнопку, расположенную ниже.",
    "Reset tags for classified files" : "Убрать все метки с обработанных файлов",
    "Click the button below to remove all face detections from all files that have been classified so far." : "Для очистки всех присвоенных меток лиц, нажмите кнопку, расположенную ниже.",
    "Reset faces for classified files" : "Убрать все метки лиц с обработанных файлов",
    "Click the button below to rescan all files in this instance and add them to the classifier queues." : "Для повторного распознавания всех файлов, нажмите кнопку, расположенную ниже.",
    "Rescan all files" : "Повторно распознать все файлы",
    "Click the button below to clear the classifier queues and clear all background jobs. This is useful when you want to do the initial classification using the terminal command." : "Нажмите кнопку ниже, чтобы очистить очереди классификаторов и очистить все фоновые задания. Это полезно, когда вы хотите выполнить начальную классификацию с помощью команды терминала.",
    "Clear queues and background jobs" : "Очистить очереди и фоновые задания",
    "Resource usage" : "Использование ресурсов",
    "By default all available CPU cores will be used which may put your system under considerable load. To avoid this, you can limit the amount of CPU Cores used. (Note: In WASM mode, currently only 1 core can be used at all times.)" : "По умолчанию будут использоваться все доступные ядра ЦП, что может привести к значительной нагрузке на вашу систему. Чтобы избежать этого, вы можете ограничить количество используемых ядер ЦП. (Примечание: в режиме WASM в настоящее время может использоваться только 1 ядро ​​в любое время.)",
    "Number of CPU Cores (0 for no limit)" : "Количество используемых ядер (0 — все доступные)",
    "By default, recognize will only ever run one classifier process at a time. If you have a lot of resources available and want to run as many processes in parallel as possible, you can turn on concurrency here." : "По умолчанию recognize будет запускать только один процесс классификатора за раз. Если у вас много доступных ресурсов и вы хотите запустить как можно больше процессов параллельно, вы можете включить параллелизм здесь.",
    "Enable unlimited concurrency of classifier processes" : "Включить неограниченное параллелизм процессов классификатора",
    "Tensorflow WASM mode" : "WASM режим Tensorflow",
    "Checking CPU" : "Проверка ЦП",
    "Could not check whether your machine supports native TensorFlow operation. Make sure your OS has GNU lib C, your CPU supports AVX instructions and you are running on x86. If one of these things is not the case, you will need to run in WASM mode." : "Не удалось проверить, поддерживает ли ваша машина собственную операцию TensorFlow. Убедитесь, что в вашей ОС есть GNU lib C, ваш процессор поддерживает инструкции AVX и вы работаете на x86. Если что-то из этого не так, вам нужно будет запустить в режиме WASM.",
    "Your machine supports native TensorFlow operation, you do not need WASM mode." : "Сервер поддерживает работу TensorFlow в непосредственном режиме, использование режима WASM не требуется.",
    "WASM mode was activated automatically, because your machine does not support native TensorFlow operation:" : "Сервер не поддерживает работу TensorFlow в непосредственном режиме, поэтому был активирован режим WASM:",
    "Enable WASM mode" : "WASM режим",
    "Recognize uses Tensorflow for running the machine learning models. Not all installations support Tensorflow, either because the CPU does not support AVX instructions, or because the platform is not x86 (ie. on a Raspberry Pi, which is ARM), or because the Operating System that your nextcloud runs on (when using docker, then that is the OS within the docker image) does not come with GNU lib C (for example Alpine Linux, which is also used by Nextcloud AIO). In most cases, even if your installation does not support native Tensorflow operation, you can still run Tensorflow using WebAssembly (WASM) in Node.js. This is somewhat slower but still works." : "Recognize использует Tensorflow для запуска моделей машинного обучения. Не все установки поддерживают Tensorflow, либо потому что ЦП не поддерживает инструкции AVX, либо потому что платформа не x86 (например, на Raspberry Pi, которая является ARM), либо потому что операционная система, на которой работает ваш nextcloud (при использовании docker, то это ОС в образе docker), не поставляется с GNU lib C (например, Alpine Linux, которая также используется Nextcloud AIO). В большинстве случаев, даже если ваша установка не поддерживает собственную работу Tensorflow, вы все равно можете запустить Tensorflow с помощью WebAssembly (WASM) в Node.js. Это немного медленнее, но все равно работает.",
    "Tensorflow GPU mode" : "Режим графического процессора Tensorflow",
    "Enable GPU mode" : "Включить режим GPU",
    "Like most machine learning models, Recognize will run even faster when using a GPU. Setting this up is non-trivial but works well when everything is setup correctly." : "Как и большинство моделей машинного обучения, Recognize будет работать еще быстрее при использовании GPU. Настройка этого нетривиальна, но работает хорошо, когда все настроено правильно.",
    "Learn how to setup GPU mode with Recognize" : "Узнайте, как настроить режим графического процессора с помощью Recognize",
    "Node.js" : "Node.js",
    "Checking Node.js" : "Выполняется проверка Node.js",
    "Node.js {version} binary was installed successfully." : "Двоичный файл библиотеки Node.js {version} успешно установлен.",
    "Checking libtensorflow" : "Выполняется проверка библиотеки libtensorflow",
    "Could not load libtensorflow in Node.js. You can try to manually install libtensorflow or run in WASM mode." : "Не удалось загрузить библиотеку libtensorflow в Node.js. Попытайтесь вручную установить libtensorflow или использовать режим работы WASM.",
    "Successfully loaded libtensorflow in Node.js, but couldn't load GPU. Make sure CUDA Toolkit and cuDNN are installed and accessible, or turn off GPU mode." : "Успешно загружен libtensorflow в Node.js, но не удалось загрузить GPU. Убедитесь, что CUDA Toolkit и cuDNN установлены и доступны, или отключите режим GPU.",
    "Libtensorflow was loaded successfully into Node.js." : "Библиотека Libtensorflow успешно загружена в Node.js.",
    "Could not load Tensorflow WASM in Node.js. Something is wrong with your setup." : "Не удалось загрузить Tensorflow WASM в Node.js. Что-то не так с вашей настройкой.",
    "Tensorflow WASM was loaded successfully into Node.js." : "Tensorflow WASM успешно загружен в Node.js.",
    "If the shipped Node.js binary doesn't work on your system for some reason you can set the path to a custom node.js binary. Currently supported is Node v20.9 and newer v20 releases." : "Если отправленный Node.js двоичный файл по какой-либо причине не работает в вашей системе, вы можете указать путь к пользовательскому node.js двоичному файлу. В настоящее время поддерживается Node версии 20.9 и более поздние версии 20.",
    "For Nextcloud Snap users, you need to adjust this path to point to the snap's \"current\" directory as the pre-configured path will change with each update. For example, set it to \"/var/snap/nextcloud/current/nextcloud/extra-apps/recognize/bin/node\" instead of \"/var/snap/nextcloud/9337974/nextcloud/extra-apps/recognize/bin/node\"" : "Пользователям Nextcloud Snap необходимо изменить этот путь, чтобы он указывал на «текущий» каталог Snap, поскольку предварительно настроенный путь будет меняться при каждом обновлении. Например, установите для него значение  \"/var/snap/nextcloud/current/nextcloud/extra-apps/recognize/bin/node\" вместо \"/var/snap/nextcloud/9337974/nextcloud/extra-apps/recognize/bin/node\"",
    "Classifier process priority" : "Приоритет процесса классификатора",
    "Checking Nice binary" : "Проверка бинарника Nice",
    "Could not find the Nice binary. You may need to set the path to a working binary manually." : "Не удалось найти бинарник Nice. Возможно, вам придется вручную указать путь к работающему бинарнику.",
    "Nice binary path" : "Хороший двоичный путь",
    "Nice value to set the priority of the Node.js processes. The value can only be from 0 to 19 since the Node.js process runs without superuser privileges. The higher the nice value, the lower the priority of the process." : "Значение nice для установки приоритета процессов Node.js. Значение может быть только от 0 до 19, так как процесс Node.js выполняется без привилегий суперпользователя. Чем выше значение nice, тем ниже приоритет процесса.",
    "Terminal commands" : "Команды для использования в консоли",
    "To download all models preliminary to executing the classification jobs, run the following command on the server terminal." : "Загрузка всех моделей машинного обучения перед распознаванием:",
    "To trigger a full classification run, run the following command on the server terminal. (The classification will run in multiple background jobs which can run in parallel.)" : "Чтобы запустить полный запуск классификации, выполните следующую команду на терминале сервера. (Классификация будет выполняться в нескольких фоновых заданиях, которые могут выполняться параллельно.)",
    "To run a full classification run on the terminal, run the following. (The classification will run in sequence inside your terminal.)" : "Чтобы запустить полную классификацию на терминале, выполните следующее. (Классификация будет выполняться последовательно внутри вашего терминала.)",
    "Before running a full initial classification run on the terminal, you should stop all background processing that Recognize scheduled upon installation to avoid interference." : "Перед запуском полного первоначального прогона классификации на терминале следует остановить всю фоновую обработку, запланированную Recognize при установке, чтобы избежать помех.",
    "To run a face clustering run on for each user in the terminal, run the following. Consider adding the parameter --batch-size 10000 for large libraries to avoid PHP memory exhaustion. (The clustering will run in sequence inside your terminal.)" : "Чтобы запустить кластеризацию лиц для каждого пользователя в терминале, выполните следующее. Рассмотрите возможность добавления параметра --batch-size 10000 для больших библиотек, чтобы избежать исчерпания памяти PHP. (Кластеризация будет выполняться последовательно внутри вашего терминала.)",
    "To remove all face clusters but keep the raw detected faces run the following on the terminal:" : "Чтобы удалить все кластеры лиц, но сохранить необработанные обнаруженные лица, выполните на терминале следующую команду:",
    "To remove all detected faces and face clusters run the following on the terminal:" : "Чтобы удалить все обнаруженные лица и кластеры лиц, выполните на терминале следующую команду:",
    "You can reset the tags of all files that have been previously classified by Recognize with the following command:" : "Очистка всех присвоенных распознаванием меток:",
    "You can delete all tags that no longer have any files associated with them with the following command:" : "Удаление всех неиспользуемых меток:",
    "To remove tags that were created by Recognize version 2 from all files run the following on the terminal:" : "Чтобы удалить теги, созданные Recognize версии 2, из всех файлов, выполните на терминале следующую команду:",
    "Cat" : "Кошка",
    "Animal" : "Животное",
    "Wildlife" : "Дикая природа",
    "Nature" : "Природа",
    "Puma" : "Пума",
    "Leopard" : "Леопард",
    "Lion" : "Лев",
    "Wild cat" : "Дикая кошка",
    "Cheetah" : "Гепард",
    "Seashore" : "Берег моря",
    "Beach" : "Пляж",
    "Water" : "Вода",
    "Lakeside" : "Берег озера",
    "Flower" : "Цветок",
    "Plant" : "Растение",
    "Window" : "Окно",
    "Architecture" : "Архитектура",
    "Stairs" : "Ступени",
    "Building" : "Строение",
    "Field" : "Поле",
    "Farm" : "Ферма",
    "Landscape" : "Ландшафт",
    "Portrait" : "Портрет",
    "People" : "Люди",
    "Fashion" : "Мода",
    "Ship" : "Корабль",
    "Vehicle" : "Транспортное средство",
    "Grasshopper" : "Кузнечик",
    "Insect" : "Насекомое",
    "Fish" : "Рыба",
    "Shark" : "Акула",
    "Chicken" : "Курица",
    "Bird" : "Птица",
    "Ostrich" : "Страус",
    "Owl" : "Сова",
    "Salamander" : "Саламандра",
    "Frog" : "Лягушка",
    "Turtle" : "Черепаха",
    "Reptile" : "Рептилия",
    "Lizard" : "Ящерица",
    "Chameleon" : "Хамелеон",
    "Crocodile" : "Крокодил",
    "Alligator" : "Аллигатор",
    "Scorpion" : "Скорпион",
    "Spider" : "Паук",
    "Duck" : "Утка",
    "Worm" : "Червь",
    "Shell" : "Ракушка",
    "Snail" : "Улитка",
    "Crab" : "Краб",
    "Lobster" : "Лобстер",
    "Cooking" : "Приготовление еды",
    "Penguin" : "Пингвин",
    "Whale" : "Кит",
    "Dog" : "Собака",
    "Wolf" : "Волк",
    "Fox" : "Лисица",
    "Bear" : "Медведь",
    "Beetle" : "Жук",
    "Butterfly" : "Бабочка",
    "Rabbit" : "Кролик",
    "Hippo" : "Гиппопотам",
    "Cow" : "Корова",
    "Buffalo" : "Бизон",
    "Sheep" : "Овца",
    "Ape" : "Обезьяна",
    "Monkey" : "Обезьяна",
    "Lemur" : "Лемур",
    "Elephant" : "Слон",
    "Panda" : "Панда",
    "Instrument" : "Инструмент",
    "Music" : "Музыка",
    "Aircraft" : "Самолёт",
    "Airport" : "Аэропорт",
    "Tractor" : "Трактор",
    "Weapon" : "Оружие",
    "Backpack" : "Рюкзак",
    "Shop" : "Магазин",
    "Office" : "Офис",
    "Outdoor" : "Улица",
    "Living" : "Жилая комната",
    "Tower" : "Башня",
    "Drinks" : "Напитки",
    "Beverage" : "Напиток",
    "Food" : "Еда",
    "Shelter" : "Убежище",
    "Furniture" : "Мебель",
    "Book" : "Книга",
    "Train" : "Поезд",
    "Butcher" : "Мясник",
    "Car" : "Автомобиль",
    "Historic" : "Историческое",
    "Boat" : "Лодка",
    "Electronics" : "Электроника",
    "Indoor" : "В помещении",
    "Church" : "Церковь",
    "Shoe" : "Обувь",
    "Candle" : "Свеча",
    "Coffee" : "Кофе",
    "Keyboard" : "Клавиатура",
    "Computer" : "Компьютер",
    "Helmet" : "Шлем",
    "Wall" : "Стена",
    "Clock" : "Часы",
    "Dining" : "За столом",
    "Kitchen" : "Кухня",
    "Snow" : "Снег",
    "Dome" : "Купол",
    "Screen" : "Экран",
    "Flag" : "Флаг",
    "Truck" : "Грузовик",
    "Store" : "Хранение",
    "Tool" : "Инструмент",
    "Pumpkin" : "Тыква",
    "Vegetables" : "Овощи",
    "Photography" : "Фотография",
    "Library" : "Библиотека",
    "Display" : "Экран",
    "Bag" : "Сумка",
    "Cup" : "Чашка",
    "Rocks" : "Скалы",
    "Bus" : "Автобус",
    "Bowl" : "Чаша",
    "Monitor" : "Монитор",
    "Bike" : "Велосипед",
    "Scooter" : "Скутер",
    "Camping" : "Поход",
    "Cart" : "Корзина",
    "Piggy bank" : "Копилка",
    "Bottle" : "Бутылка",
    "Plate" : "Тарелка",
    "Camera" : "Камера",
    "Camper" : "Кемпер",
    "Barbecue" : "Барбекю",
    "Basket" : "Корзина",
    "Diving" : "Подводное плавание",
    "Snowmobile" : "Снегоход",
    "Bridge" : "Мост",
    "Couch" : "Диван",
    "Theater" : "Театр",
    "Spoon" : "Ложка",
    "Comic" : "Юмор",
    "Soup" : "Суп",
    "Dessert" : "Десерт",
    "Bakery" : "Выпечка",
    "Fruit" : "Фрукт",
    "Pasta" : "Паста",
    "Meat" : "Мясо",
    "Pizza" : "Пицца",
    "Wine" : "Вино",
    "Alpine" : "Альпы",
    "Mountains" : "Горы",
    "Sand" : "Песок",
    "Wool" : "Шерсть",
    "Glass" : "Стакан",
    "Moment" : "Момент",
    "Info" : "Информация",
    "Document" : "Документ",
    "Puzzle" : "Головоломка",
    "Heritage" : "Наследие",
    "Safe" : "Безопасность",
    "Bucket" : "Корзина",
    "Baby" : "Малыш",
    "Cradle" : "Колыбель",
    "Patio" : "Внутренний дворик",
    "Mountain" : "Гора",
    "Radio telescope" : "Радиотелескоп",
    "Theme park" : "Тематический парк",
    "Festival" : "Фестиваль",
    "Event" : "Событие",
    "Monument" : "Памятник",
    "Balloon" : "Воздушный шар",
    "Crib" : "Детская кровать",
    "Fan" : "Вентилятор",
    "Gas station" : "Заправка",
    "Wood" : "Дерево",
    "Bench" : "Скамейка",
    "Parking" : "Парковка",
    "Traffic" : "Дорожная пробка",
    "Public transport" : "Общественный транспорт",
    "Umbrella" : "Зонтик",
    "Stage" : "Сцена",
    "Toy" : "Игрушка",
    "Vase" : "Ваза",
    "Mailbox" : "Почтовый ящик",
    "Sign" : "Знак",
    "Gallery" : "Галерея",
    "Park" : "Парк"
},
"nplurals=4; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 || n%100>14) ? 1 : n%10==0 || (n%10>=5 && n%10<=9) || (n%100>=11 && n%100<=14)? 2 : 3);");
