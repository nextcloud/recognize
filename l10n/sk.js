OC.L10N.register(
    "recognize",
    {
    "Recognize" : "Rozpoznávanie",
    "Smart media tagging and face recognition with on-premises machine learning models" : "Inteligentné označovanie médií a rozpoznávanie tváre s lokálnymi modelmi strojového učenia",
    "Smart media tagging and face recognition with on-premises machine learning models.\nThis app goes through your media collection and adds fitting tags, automatically categorizing your photos and music.\n\n* 📷 👪 Recognizes faces from contact photos\n* 📷 🏔 Recognizes animals, landscapes, food, vehicles, buildings and other objects\n* 📷 🗼 Recognizes landmarks and monuments\n* 👂 🎵 Recognizes music genres\n* 🎥 🤸 Recognizes human actions on video\n\n⚡ Tagging works via Nextcloud's Collaborative Tags\n  * 👂 listen to your tagged music with the audioplayer app\n  * 📷 view your tagged photos and videos with the photos app\n\nModel sizes:\n\n * Object recognition: 1GB\n * Landmark recognition: 300MB\n * Video action recognition: 50MB\n * Music genre recognition: 50MB\n\n## Ethical AI Rating\n### Rating for Photo object detection: 🟢\n\nPositive:\n* the software for training and inference of this model is open source\n* the trained model is freely available, and thus can be run on-premises\n* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.\n\n### Rating for Photo face recognition: 🟢\n\nPositive:\n* the software for training and inference of this model is open source\n* the trained model is freely available, and thus can be run on-premises\n* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.\n\n### Rating for Video action recognition: 🟢\n\nPositive:\n* the software for training and inferencing of this model is open source\n* the trained model is freely available, and thus can be ran on-premises\n* the training data is freely available, making it possible to check or correct for bias or optimise the performance and CO2 usage.\n\n## Ethical AI Rating\n### Rating Music genre recognition: 🟡\n\nPositive:\n* the software for training and inference of this model is open source\n* the trained model is freely available, and thus can be run on-premises\n\nNegative:\n* the training data is not freely available, limiting the ability of external parties to check and correct for bias or optimise the model’s performance and CO2 usage.\n\nLearn more about the Nextcloud Ethical AI Rating [in our blog](https://nextcloud.com/blog/nextcloud-ethical-ai-rating/).\n\nAfter installation, you can enable tagging in the admin settings.\n\nRequirements:\n- php 7.4 and above\n- App \"collaborative tags\" enabled\n- For native speed:\n  - Processor: x86 64-bit (with support for AVX instructions)\n  - System with glibc (usually the norm on Linux; FreeBSD, Alpine linux and thus also the official Nextcloud Docker container and Nextcloud AIO are *not* such systems)\n- For sub-native speed (using WASM mode)\n  - Processor: x86 64-bit, arm64, armv7l (no AVX needed)\n  - System with glibc or musl (incl. Alpine linux and thus also the official Nextcloud Docker container and also Nextcloud AIO)\n- ~4GB of free RAM (if you're cutting it close, make sure you have some swap available)\n\nThe app does not send any sensitive data to cloud providers or similar services. All processing is done on your Nextcloud machine, using Tensorflow.js running in Node.js." : "Inteligentné označovanie médií a rozpoznávanie tváre s lokálnymi modelmi strojového učenia.\nTáto aplikácia prechádza vašou zbierkou médií a pridáva vhodné značky, pričom automaticky kategorizuje vaše fotografie a hudbu.\n\n* 📷 👪 Rozpoznáva tváre z fotografií kontaktov\n* 📷 🏔 Rozpoznáva zvieratá, krajinu, jedlo, vozidlá, budovy a iné predmety\n* 📷 🗼 Rozpoznáva orientačné body a pamiatky\n* 👂 🎵 Rozpoznáva hudobné žánre\n* 🎥 🤸 Rozpoznáva ľudské činy na videu\n\n⚡ Označovanie funguje prostredníctvom Collaborative Tags od Nextcloud\n* 👂 počúvajte svoju označenú hudbu pomocou aplikácie audioprehrávača\n* 📷 prezerajte si označené fotografie a videá pomocou aplikácie Fotografie\n\nVeľkosti modelu:\n\n* Rozpoznávanie objektov: 1 GB\n* Rozpoznávanie orientačných bodov: 300 MB\n* Rozpoznávanie akcií videa: 50 MB\n* Rozpoznanie hudobného žánru: 50 MB\n\n## Etické hodnotenie AI\n### Hodnotenie detekcie fotografického objektu: 🢢\n\nPozitívne:\n* softvér na školenie a odvodzovanie tohto modelu je open source\n* natrénovaný model je voľne dostupný, a teda môže byť prevádzkovaný na mieste\n* tréningové údaje sú voľne dostupné, čo umožňuje kontrolovať alebo korigovať skreslenie alebo optimalizovať výkon a spotrebu CO2.\n\n### Hodnotenie pre rozpoznávanie tváre pomocou fotografií: 🢢\n\nPozitívne:\n* softvér na školenie a odvodzovanie tohto modelu je open source\n* natrénovaný model je voľne dostupný, a teda môže byť prevádzkovaný na mieste\n* tréningové údaje sú voľne dostupné, čo umožňuje kontrolovať alebo korigovať skreslenie alebo optimalizovať výkon a spotrebu CO2.\n\n### Hodnotenie pre rozpoznávanie akcie videa: 🢢\n\nVýhody:\n* softvér na školenie a odvodzovanie tohto modelu je open source\n* natrénovaný model je voľne dostupný, a preto je možné ho spustiť v priestoroch\n* tréningové údaje sú voľne dostupné, čo umožňuje kontrolovať alebo korigovať skreslenie alebo optimalizovať výkon a spotrebu CO2.\n\n## Etické hodnotenie AI\n### Hodnotenie Rozpoznanie hudobného žánru:\n\nVýhody:\n* softvér na školenie a odvodzovanie tohto modelu je open source\n* natrénovaný model je voľne dostupný, a teda môže byť prevádzkovaný na mieste\n\nNevýhody:\n* tréningové údaje nie sú voľne dostupné, čo obmedzuje možnosť externých strán kontrolovať a opravovať zaujatosť alebo optimalizovať výkon modelu a spotrebu CO2.\n\nPrečítajte si viac o hodnotení Nextcloud Ethical AI Rating [v našom blogu](https://nextcloud.com/blog/nextcloud-ethical-ai-rating/).\n\nPo inštalácii môžete povoliť označovanie v nastaveniach správcu.\n\nPožiadavky:\n- php 7.4 a vyššie\n- Povolené „spoločné značky“ aplikácie\n- Pre natívnu rýchlosť:\n- Procesor: x86 64-bit (s podporou inštrukcií AVX)\n- Systém s glibc (zvyčajne norma na Linuxe; FreeBSD, Alpine linux a teda aj oficiálny kontajner Nextcloud Docker a Nextcloud AIO *nie sú* takéto systémy)\n- Pre sub-native rýchlosť (pomocou režimu WASM)\n- Procesor: x86 64-bit, arm64, armv7l (nie je potrebný AVX)\n- Systém s glibc alebo musl (vrátane Alpine linux a teda aj oficiálneho kontajnera Nextcloud Docker a tiež Nextcloud AIO)\n- ~4 GB voľnej pamäte RAM (ak ju obmedzujete, uistite sa, že máte k dispozícii nejaký swap)\n\nAplikácia neposiela žiadne citlivé údaje poskytovateľom cloudu ani podobným službám. Všetko spracovanie sa vykonáva na vašom počítači Nextcloud pomocou Tensorflow.js spusteného v Node.js.",
    "Your server does not support AVX instructions" : "Váš server nepodporuje AVX inštrukcie",
    "Your server does not have an x86 64-bit CPU" : "Váš server nemá x86 64-bitové CPU",
    "Your server uses musl libc" : "Váš server používa muls libc",
    "Failed to load settings" : "Nepodarilo sa načítať nastavenia",
    "Failed to save settings" : "Nepodarilo sa uložiť nastavenia",
    "never" : "nikdy",
    "{time} ago" : "Pred {time} ",
    "Status" : "Stav",
    "The machine learning models have been downloaded successfully." : "Modely strojového učenia boli úspešne stiahnuté.",
    "The machine learning models still need to be downloaded." : "Stále je potrebné stiahnuť modely strojového učenia.",
    "Could not execute the Node.js binary. You may need to set the path to a working binary manually." : "Nepodarilo sa spustiť binárny súbor Node.js. Možno budete musieť manuálne nastaviť cestu k fungujúcemu binárnemu súboru.",
    "Background Jobs are not executed via cron. Recognize requires background jobs to be executed via cron." : "Úlohy na pozadí sa nevykonávajú cez cron. Aplikácia Rozpoznávanie vyžaduje, aby sa úlohy na pozadí vykonávali cez cron.",
    "The app is installed and will automatically classify files in background processes." : "Aplikácia je nainštalovaná a automaticky klasifikuje súbory v procese na pozadí.",
    "None of the tagging options below are currently selected. The app will currently do nothing." : "Momentálne nie je vybratá žiadna z nižšie uvedených možností označovania štítkom. Aplikácia momentálne nebude robiť nič.",
    "Face recognition" : "Rozpoznávanie tváre",
    "Face recognition is working. " : "Rozpoznávanie tváre je funkčné.",
    "An error occurred during face recognition, please check the Nextcloud logs." : "Počas rozpoznávania tváre sa vyskytla chyba, skontrolujte záznam o chybách Nextcloud.",
    "Waiting for status reports on face recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Čaká sa na správy o stave rozpoznávania tváre. Ak táto správa pretrváva dlhšie ako 15 minút, skontrolujte denníky Nextcloud.",
    "Face recognition:" : "Rozpoznávanie tváre:",
    "Queued files" : "Súbory vo fronte",
    "Last classification: " : "Posledná klasifikácia:",
    "Scheduled background jobs: " : "Naplánované úlohy na pozadí:",
    "Last background job execution: " : "Posledné spustenie úloh na pozadí:",
    "There are queued files in the face recognition queue but no background job is scheduled to process them." : "Vo fronte sú zaradené súbory na rozpoznávanie tváre, ale na ich spracovanie nie je naplánovaná žiadna úloha na pozadí.",
    "Face clustering:" : "Zoskupovanie tvárí:",
    "faces left to cluster" : "tváre zostali zoskupené",
    "Last clustering run: " : "Naposledy spustené zoskupovanie:",
    "A minimum of 120 faces per user is necessary for clustering to kick in" : "Na spustenie zoskupovania je potrebných minimálne 120 tvárí na používateľa",
    "Enable face recognition (groups photos by faces that appear in them; UI is in the photos app)" : "Povoliť rozpoznávanie tvárí (zoskupuje fotografie podľa tvárí, ktoré sa na nich zobrazujú; používateľské rozhranie je v aplikácii Fotky)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~500 or more, in WASM mode ~50 is recommended)" : "Počet súborov na spracovanie pre spustenie úlohy (Úloha bude naplánovaná každých 5 minút; pre normálnu prevádzku ~500 alebo viac, v režime WASM sa odporúča ~50)",
    "Object detection & landmark recognition" : "Detekcia objektov a rozpoznávanie orientačných bodov",
    "Object recognition is working." : "Rozpoznávanie objektov je funkčné.",
    "An error occurred during object recognition, please check the Nextcloud logs." : "Počas rozpoznávania objektu sa vyskytla chyba, skontrolujte záznam o chybách Nextcloud.",
    "Waiting for status reports on object recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Čaká sa na správy o stave rozpoznávania objektu. Ak táto správa pretrváva dlhšie ako 15 minút, skontrolujte protokoly Nextcloud.",
    "Object recognition:" : "Rozpoznávanie objektov:",
    "There are queued files in the object detection queue but no background job is scheduled to process them." : "Vo fronte na detekciu objektov sú zaradené súbory, ale na ich spracovanie nie je naplánovaná žiadna úloha na pozadí.",
    "Landmark recognition is working." : "Rozpoznanie orientačných bodov funguje.",
    "An error occurred during landmark recognition, please check the Nextcloud logs." : "Počas rozpoznávania orientačného bodu sa vyskytla chyba, skontrolujte protokoly Nextcloud.",
    "Waiting for status reports on landmark recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Čaká sa na správy o stave rozpoznávania orientačných bodov. Ak táto správa pretrváva dlhšie ako 15 minút, skontrolujte protokoly Nextcloud.",
    "Landmark recognition:" : "Rozpoznávanie orientačných bodov:",
    "There are queued files in the landmarks queue but no background job is scheduled to process them." : "Vo fronte orientačných bodov sú zaradené súbory, ale na ich spracovanie nie je naplánovaná žiadna úloha na pozadí.",
    "Enable object recognition (e.g. food, vehicles, landscapes)" : "Povoliť rozpoznávanie objektov (napr. jedlo, vozidlá, krajina)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~100 or more, in WASM mode ~20 is recommended)" : "Počet súborov na spracovanie pre spustenie úlohy (úloha bude naplánovaná každých 5 minút; pre normálnu prevádzku sa odporúča ~100 alebo viac, v režime WASM sa odporúča ~20)",
    "Enable landmark recognition (e.g. Eiffel Tower, Golden Gate Bridge)" : "Povoliť rozpoznávanie orientačných bodov (napr. Eiffelova veža, Golden Gate Bridge)",
    "Audio tagging" : "Taggovanie audia",
    "Audio recognition is working." : "Rozpoznávanie audia je funkčné.",
    "An error occurred during audio recognition, please check the Nextcloud logs." : "Počas rozpoznávania audia sa vyskytla chyba, skontrolujte protokoly Nextcloud.",
    "Waiting for status reports on audio recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Čaká sa na správy o stave rozpoznávania zvuku. Ak táto správa pretrváva dlhšie ako 15 minút, skontrolujte protokoly Nextcloud.",
    "Music genre recognition:" : "Rozpoznávanie hudobného žánru:",
    "There are queued files but no background job is scheduled to process them." : "Existujú súbory vo fronte, ale na ich spracovanie nie je naplánovaná žiadna úloha na pozadí.",
    "Enable music genre recognition (e.g. pop, rock, folk, metal, new age)" : "Povoliť rozpoznávanie hudobných žánrov (napr. pop, rock, folk, metal, new age)",
    "Video tagging" : "Taggovanie videa",
    "Video recognition is working." : "Rozpoznávanie videa je funkčné.",
    "An error occurred during video recognition, please check the Nextcloud logs." : "Počas rozpoznávania videa sa vyskytla chyba, skontrolujte protokoly Nextcloud.",
    "Waiting for status reports on video recognition. If this message persists beyond 15 minutes, please check the Nextcloud logs." : "Čaká sa na správy o stave rozpoznávania videa. Ak táto správa pretrváva dlhšie ako 15 minút, skontrolujte protokoly Nextcloud.",
    "Video recognition:" : "Rozpoznávanie videa:",
    "Enable human action recognition (e.g. arm wrestling, dribbling basketball, hula hooping)" : "Povoliť rozpoznávanie ľudskej činnosti (napr. pretláčanie rukou, driblovanie v basketbale, hula hooping)",
    "The number of files to process per job run (A job will be scheduled every 5 minutes; For normal operation ~20 or more, in WASM mode ~5 is recommended)" : "Počet súborov na spracovanie pre spustenie úlohy (úloha bude naplánovaná každých 5 minút; pre normálnu prevádzku sa odporúča ~20 alebo viac, v režime WASM sa odporúča ~5)",
    "Reset" : "Resetovať",
    "Click the button below to remove all tags from all files that have been classified so far." : "Kliknutím na tlačidlo nižšie odstránite všetky značky zo všetkých súborov, ktoré boli doteraz klasifikované.",
    "Reset tags for classified files" : "Obnoviť štítky pre klasifikované súbory",
    "Click the button below to remove all face detections from all files that have been classified so far." : "Kliknutím na tlačidlo nižšie odstránite všetky detekcie tváre zo všetkých súborov, ktoré boli doteraz klasifikované.",
    "Reset faces for classified files" : "Obnoviť tváre pre klasifikované súbory",
    "Click the button below to rescan all files in this instance and add them to the classifier queues." : "Kliknutím na tlačidlo nižšie znova prehľadáte všetky súbory v tejto inštancii a pridáte ich do fronty klasifikátorov.",
    "Rescan all files" : "Znova skenovať všetky súbory",
    "Click the button below to clear the classifier queues and clear all background jobs. This is useful when you want to do the initial classification using the terminal command." : "Kliknutím na tlačidlo nižšie vymažete fronty klasifikátora a všetky úlohy na pozadí. Je to užitočné, keď chcete vykonať počiatočnú klasifikáciu pomocou príkazu terminálu.",
    "Clear queues and background jobs" : "Vyčistiť fronty a úlohy na pozadí",
    "Resource usage" : "Využitie zdrojov",
    "By default all available CPU cores will be used which may put your system under considerable load. To avoid this, you can limit the amount of CPU Cores used. (Note: In WASM mode, currently only 1 core can be used at all times.)" : "V predvolenom nastavení sa použijú všetky dostupné jadrá CPU, čo môže spôsobiť značné zaťaženie vášho systému. Aby ste tomu zabránili, môžete obmedziť množstvo použitých jadier CPU. (Poznámka: V režime WASM je v súčasnosti možné použiť vždy iba 1 jadro.)",
    "Number of CPU Cores (0 for no limit)" : "Počet CPU jadier (0 pre žiadny limit)",
    "By default, recognize will only ever run one classifier process at a time. If you have a lot of resources available and want to run as many processes in parallel as possible, you can turn on concurrency here." : "V predvolenom nastavení rozpoznávanie vždy spustí iba jeden proces klasifikátora naraz. Ak máte k dispozícii veľa zdrojov a chcete paralelne spúšťať čo najviac procesov, môžete tu zapnúť súbežnosť.",
    "Enable unlimited concurrency of classifier processes" : "Povoliť neobmedzenú súbežnosť procesov klasifikátora",
    "Tensorflow WASM mode" : "Režim Tensorflow WASM",
    "Checking CPU" : "Kontrolujem CPU",
    "Could not check whether your machine supports native TensorFlow operation. Make sure your OS has GNU lib C, your CPU supports AVX instructions and you are running on x86. If one of these things is not the case, you will need to run in WASM mode." : "Nepodarilo sa skontrolovať, či váš počítač podporuje natívnu prevádzku TensorFlow. Uistite sa, že váš OS má GNU lib C, váš procesor podporuje inštrukcie AVX a že používate x86. Ak jedna z týchto vecí nie je splnená, budete musieť spustiť režim WASM.",
    "Your machine supports native TensorFlow operation, you do not need WASM mode." : "Váš systém podporuje natívne operácie TensorFlow, nepotrebujete WASM mód.",
    "WASM mode was activated automatically, because your machine does not support native TensorFlow operation:" : "Režim WASM bol aktivovaný automaticky, pretože váš stroj nepodporuje natívnu prevádzku TensorFlow:",
    "Enable WASM mode" : "Povoliť WASM mód",
    "Recognize uses Tensorflow for running the machine learning models. Not all installations support Tensorflow, either because the CPU does not support AVX instructions, or because the platform is not x86 (ie. on a Raspberry Pi, which is ARM), or because the Operating System that your nextcloud runs on (when using docker, then that is the OS within the docker image) does not come with GNU lib C (for example Alpine Linux, which is also used by Nextcloud AIO). In most cases, even if your installation does not support native Tensorflow operation, you can still run Tensorflow using WebAssembly (WASM) in Node.js. This is somewhat slower but still works." : "Rozpoznávanie používa Tensorflow na spustenie modelov strojového učenia. Nie všetky inštalácie podporujú Tensorflow, buď preto, že CPU nepodporuje inštrukcie AVX, alebo preto, že platforma nie je x86 (tj na Raspberry Pi, čo je ARM), alebo preto, že operačný systém, na ktorom beží váš nextcloud (pri použití dockeru , potom je to OS v dockerovom obraze) sa nedodáva s GNU lib C (napríklad Alpine Linux, ktorý používa aj Nextcloud AIO). Vo väčšine prípadov, aj keď vaša inštalácia nepodporuje natívnu prevádzku Tensorflow, stále môžete spustiť Tensorflow pomocou WebAssembly (WASM) v Node.js. Toto je o niečo pomalšie, ale stále funguje.",
    "Tensorflow GPU mode" : "Režim Tensorflow GPU",
    "Enable GPU mode" : "Povoliť režim GPU",
    "Like most machine learning models, Recognize will run even faster when using a GPU. Setting this up is non-trivial but works well when everything is setup correctly." : "Ako väčšina modelov strojového učenia, aj Rozpoznávanie pobeží pri použití GPU ešte rýchlejšie. Toto nastavenie nie je triviálne, ale funguje dobre, keď je všetko nastavené správne.",
    "Learn how to setup GPU mode with Recognize" : "Zistite, ako nastaviť režim GPU s funkciou Rozpoznávanie",
    "Node.js" : "Node.js",
    "Checking Node.js" : "Overuje sa Node.js",
    "Node.js {version} binary was installed successfully." : "Node.js {version} bol úspešne nainštalovaný.",
    "Checking libtensorflow" : "Overuje sa libtensorflow",
    "Could not load libtensorflow in Node.js. You can try to manually install libtensorflow or run in WASM mode." : "Nepodarilo sa načítať libtensorflow v Node.js. Môžete skúsiť manuálne nainštalovať libtensorflow alebo spustiť režim  WASM.",
    "Successfully loaded libtensorflow in Node.js, but couldn't load GPU. Make sure CUDA Toolkit and cuDNN are installed and accessible, or turn off GPU mode." : "Úspešne sa načítal libtensorflow v Node.js, ale nepodarilo sa načítať GPU. Uistite sa, že CUDA Toolkit a cuDNN sú nainštalované a dostupné, alebo vypnite režim GPU.",
    "Libtensorflow was loaded successfully into Node.js." : "Libtensorflow bol úspešne načítaný v Node.js",
    "Could not load Tensorflow WASM in Node.js. Something is wrong with your setup." : "Nepodarilo sa načítať Tensorflow WASM v Node.js. Niečo nie je v poriadku s vaším nastavením.",
    "Tensorflow WASM was loaded successfully into Node.js." : "Tensorflow WASM bol úspešne načítaný v Node.js",
    "If the shipped Node.js binary doesn't work on your system for some reason you can set the path to a custom node.js binary. Currently supported is Node v20.9 and newer v20 releases." : "Ak dodaný binárny súbor Node.js vo vašom systéme z nejakého dôvodu nefunguje, môžete nastaviť cestu k vlastnému binárnemu súboru node.js. V súčasnosti je podporovaný Node v20.9 a novšie v20 vydania.",
    "For Nextcloud Snap users, you need to adjust this path to point to the snap's \"current\" directory as the pre-configured path will change with each update. For example, set it to \"/var/snap/nextcloud/current/nextcloud/extra-apps/recognize/bin/node\" instead of \"/var/snap/nextcloud/9337974/nextcloud/extra-apps/recognize/bin/node\"" : "Pre používateľov Nextcloud Snap musíte túto cestu upraviť tak, aby ukazovala na „aktuálny“ adresár snapu, pretože vopred nakonfigurovaná cesta sa bude meniť s každou aktualizáciou. Nastavte ho napríklad na „/var/snap/nextcloud/current/nextcloud/extra-apps/recognize/bin/node“ namiesto „/var/snap/nextcloud/9337974/nextcloud/extra-apps/recognize/bin/ node\"",
    "Classifier process priority" : "Priorita procesu klasifikátora",
    "Checking Nice binary" : "Overuje sa Nice",
    "Could not find the Nice binary. You may need to set the path to a working binary manually." : "Nice sa nepodarilo nájsť. Možno budete musieť manuálne nastaviť cestu k fungujúcemu binárnemu súboru.",
    "Nice binary path" : "Cesta k binárnemu súboru Nice",
    "Nice value to set the priority of the Node.js processes. The value can only be from 0 to 19 since the Node.js process runs without superuser privileges. The higher the nice value, the lower the priority of the process." : "Hodnota Nice na nastavenie priority procesov Node.js. Hodnota môže byť iba od 0 do 19, pretože proces Node.js beží bez privilégií superužívateľa. Čím vyššia je hodnota nice, tým nižšia je priorita procesu.",
    "Terminal commands" : "Príkazy terminálu",
    "To download all models preliminary to executing the classification jobs, run the following command on the server terminal." : "Ak chcete stiahnuť všetky modely pred vykonaním klasifikačných úloh, spustite nasledujúci príkaz na serverovom termináli.",
    "To trigger a full classification run, run the following command on the server terminal. (The classification will run in multiple background jobs which can run in parallel.)" : "Ak chcete spustiť úplný chod klasifikácie, spustite nasledujúci príkaz na serverovom termináli. (Klasifikácia bude prebiehať vo viacerých úlohách na pozadí, ktoré môžu bežať paralelne.)",
    "To run a full classification run on the terminal, run the following. (The classification will run in sequence inside your terminal.)" : "Ak chcete spustiť úplnú klasifikáciu na termináli, spustite nasledujúce. (Klasifikácia bude prebiehať postupne vo vašom termináli.)",
    "Before running a full initial classification run on the terminal, you should stop all background processing that Recognize scheduled upon installation to avoid interference." : "Pred spustením úplnej úvodnej klasifikácie na termináli by ste mali zastaviť všetko spracovanie na pozadí, ktoré je naplánované pre rozpoznanie po inštalácii, aby ste predišli rušeniu.",
    "To run a face clustering run on for each user in the terminal, run the following. Consider adding the parameter --batch-size 10000 for large libraries to avoid PHP memory exhaustion. (The clustering will run in sequence inside your terminal.)" : "Ak chcete spustiť zoskupovanie tvárí pre každého používateľa v termináli, spustite nasledujúce. Zvážte pridanie parametra --batch-size 10000 pre veľké knižnice, aby ste sa vyhli vyčerpaniu pamäte PHP. (Zhlukovanie bude prebiehať postupne vo vašom termináli.)",
    "To remove all face clusters but keep the raw detected faces run the following on the terminal:" : "Ak chcete odstrániť všetky zoskupenia tvárí, ale zachovať nespracované rozpoznané tváre, spustite na termináli nasledovné:",
    "To remove all detected faces and face clusters run the following on the terminal:" : "Ak chcete odstrániť všetky rozpoznané tváre a zoskupenia tvárí, spustite na termináli nasledovné:",
    "You can reset the tags of all files that have been previously classified by Recognize with the following command:" : "Pomocou nasledujúceho príkazu môžete obnoviť značky všetkých súborov, ktoré boli predtým klasifikované pomocou funkcie Rozpoznávanie:",
    "You can delete all tags that no longer have any files associated with them with the following command:" : "Pomocou nasledujúceho príkazu môžete odstrániť všetky štítky, ku ktorým už nie sú priradené žiadne súbory:",
    "To remove tags that were created by Recognize version 2 from all files run the following on the terminal:" : "Ak chcete odstrániť značky, ktoré boli vytvorené programom Rozpoznávanie verzie 2 zo všetkých súborov, spustite na termináli nasledovné:",
    "Cat" : "Mačka",
    "Animal" : "Zviera",
    "Wildlife" : "Divoká príroda",
    "Nature" : "Príroda",
    "Puma" : "Puma",
    "Leopard" : "Leopard",
    "Lion" : "Lev",
    "Wild cat" : "Divoká mačka",
    "Cheetah" : "Gepard",
    "Seashore" : "Morské pobrežie",
    "Beach" : "Pláž",
    "Water" : "Voda",
    "Lakeside" : "Pri jazere",
    "Flower" : "Kvetina",
    "Plant" : "Rastlina",
    "Window" : "Okno",
    "Architecture" : "Architektúra",
    "Stairs" : "Schody",
    "Building" : "Budova",
    "Field" : "Pole",
    "Farm" : "Farma",
    "Landscape" : "Na šírku",
    "Portrait" : "Na výšku",
    "People" : "Ľudia",
    "Fashion" : "Móda",
    "Ship" : "Loď",
    "Vehicle" : "Vozidlo",
    "Grasshopper" : "Kobylka",
    "Insect" : "Hmyz",
    "Fish" : "Ryba",
    "Shark" : "Žralok",
    "Chicken" : "Kura",
    "Bird" : "Vták",
    "Ostrich" : "Pštros",
    "Owl" : "Sova",
    "Salamander" : "Salamander",
    "Frog" : "Žaba",
    "Turtle" : "Korytnačka",
    "Reptile" : "Plaz",
    "Lizard" : "Jašterica",
    "Chameleon" : "Chameleon",
    "Crocodile" : "Krokodíl",
    "Alligator" : "Aligátor",
    "Scorpion" : "Škorpión",
    "Spider" : "Pavúk",
    "Duck" : "Kačica",
    "Worm" : "Červ",
    "Shell" : "Škrupina",
    "Snail" : "Slimák",
    "Crab" : "Krab",
    "Lobster" : "Homár",
    "Cooking" : "Varenie",
    "Penguin" : "Tučniak",
    "Whale" : "Veľryba",
    "Dog" : "Pes",
    "Wolf" : "Vlk",
    "Fox" : "Líška",
    "Bear" : "Medveď",
    "Beetle" : "Chrobák",
    "Butterfly" : "Motýľ",
    "Rabbit" : "Zajac",
    "Hippo" : "Hroch",
    "Cow" : "Krava",
    "Buffalo" : "Byvol",
    "Sheep" : "Ovca",
    "Ape" : "Op",
    "Monkey" : "Opica",
    "Lemur" : "Lemur",
    "Elephant" : "Slon",
    "Panda" : "Panda",
    "Instrument" : "Nástroj",
    "Music" : "Hudba",
    "Aircraft" : "Lietadlo",
    "Airport" : "Letisko",
    "Tractor" : "Traktor",
    "Weapon" : "Zbraň",
    "Backpack" : "Batoh",
    "Shop" : "Obchod",
    "Office" : "Office",
    "Outdoor" : "Outdoor",
    "Living" : "Život",
    "Tower" : "Veža",
    "Drinks" : "Nápoje",
    "Beverage" : "Nápoj",
    "Food" : "Jedlo",
    "Shelter" : "Prístrešok",
    "Furniture" : "Nábytok",
    "Book" : "Kniha",
    "Train" : "Vlak",
    "Butcher" : "Mäsiar",
    "Car" : "Auto",
    "Historic" : "Historické",
    "Boat" : "Loď",
    "Electronics" : "Elektronika",
    "Indoor" : "Indoor",
    "Church" : "Kostol",
    "Shoe" : "Topánka",
    "Candle" : "Sviečka",
    "Coffee" : "Káva",
    "Keyboard" : "Klávesnica",
    "Computer" : "Počítač",
    "Helmet" : "Helma",
    "Wall" : "Stena",
    "Clock" : "Hodiny",
    "Dining" : "Stolovanie",
    "Kitchen" : "Kuchyňa",
    "Snow" : "Sneh",
    "Dome" : "Kupola",
    "Screen" : "Obrazovka",
    "Flag" : "Vlajka",
    "Truck" : "Nákladné auto",
    "Store" : "Obchod",
    "Tool" : "Nástroj",
    "Pumpkin" : "Tekvica",
    "Vegetables" : "Zelenina",
    "Photography" : "Fotografia",
    "Library" : "Knižnica",
    "Display" : "Zobrazenie",
    "Bag" : "Taška",
    "Cup" : "Pohár",
    "Rocks" : "Skaly",
    "Bus" : "Autobus",
    "Bowl" : "Misa",
    "Monitor" : "Monitor",
    "Bike" : "Bycikel",
    "Scooter" : "Skúter",
    "Camping" : "Táborenie",
    "Cart" : "Vozík",
    "Piggy bank" : "Pokladnička",
    "Bottle" : "Flaška",
    "Plate" : "Tanier",
    "Camera" : "Kamera",
    "Camper" : "Rekreant",
    "Barbecue" : "Grilovanie",
    "Basket" : "Košík",
    "Diving" : "Potápanie",
    "Snowmobile" : "Snežný skúter",
    "Bridge" : "Most",
    "Couch" : "Gauč",
    "Theater" : "Divadlo",
    "Spoon" : "Lyžička",
    "Comic" : "Smiešne",
    "Soup" : "Polievka",
    "Dessert" : "Dezert",
    "Bakery" : "Pečivo",
    "Fruit" : "Ovocie",
    "Pasta" : "Cestoviny",
    "Meat" : "Mäso",
    "Pizza" : "Pizza",
    "Wine" : "Víno",
    "Alpine" : "Vysokohorské",
    "Mountains" : "Hory",
    "Sand" : "Piesok",
    "Wool" : "Vlna",
    "Glass" : "Sklo",
    "Moment" : "Momentka",
    "Info" : "Info",
    "Document" : "Dokument",
    "Puzzle" : "Hlavolam",
    "Heritage" : "Dedičstvo",
    "Safe" : "Bezpečie",
    "Bucket" : "Sektor",
    "Baby" : "Dieťa",
    "Cradle" : "Kolíska",
    "Patio" : "Terasa",
    "Mountain" : "Vrch",
    "Radio telescope" : "Rádioteleskop",
    "Theme park" : "Tématický park",
    "Festival" : "Festival",
    "Event" : "Udalosť",
    "Monument" : "Pamätník",
    "Balloon" : "Balón",
    "Crib" : "Postieľka",
    "Fan" : "Ventilátor",
    "Gas station" : "Čerpacia stanica",
    "Wood" : "Drevo",
    "Bench" : "Lavica",
    "Parking" : "Parkovanie",
    "Traffic" : "Doprava",
    "Public transport" : "Verejná doprava",
    "Umbrella" : "Dáždnik",
    "Stage" : "Divadlo",
    "Toy" : "Hračka",
    "Vase" : "Váza",
    "Mailbox" : "Poštová schránka",
    "Sign" : "Podpísať",
    "Gallery" : "Galéria",
    "Park" : "Park"
},
"nplurals=4; plural=(n % 1 == 0 && n == 1 ? 0 : n % 1 == 0 && n >= 2 && n <= 4 ? 1 : n % 1 != 0 ? 2: 3);");
