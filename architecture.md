# Architecture

This document describes the internal structure of `php-io-cli` and how its layers relate to one another.

---

## High-level layer map

```mermaid
graph TD
    APP["CLIApplication\n(entry point / dispatcher)"]
    CMD["AbstractCommand\n(your commands extend this)"]
    IO["IOInterface\n(unified I/O contract)"]
    CIO["ConsoleIO\n(real TTY — delegates to components)"]
    BIO["BufferIO\n(in-memory — for testing)"]
    NIO["NullIO\n(silent — for daemons / CI)"]
    PROMPT["AbstractPrompt\n(reactive event loop)"]
    COMP["Components\n(TextInput · Select · Table · …)"]
    DEP["Depends\n(State · Input · Terminal · Colors · Shell · …)"]

    APP --> CMD
    CMD --> IO
    IO --> CIO
    IO --> BIO
    IO --> NIO
    CIO --> PROMPT
    PROMPT --> COMP
    COMP --> DEP
```

---

## Component lifecycle

Every interactive component (TextInput, Select, Confirm, …) extends `Component → AbstractPrompt` and runs through this lifecycle inside `run()`:

```mermaid
sequenceDiagram
    participant Caller
    participant AbstractPrompt
    participant Component
    participant Terminal
    participant Input
    participant State

    Caller->>AbstractPrompt: run()
    AbstractPrompt->>Terminal: enableRaw()
    AbstractPrompt->>Component: mount() → setup()
    Note over Component: wire State + Input bindings

    loop Until stop() is called
        AbstractPrompt->>Component: render()
        Component-->>Terminal: echo ANSI output
        AbstractPrompt->>Terminal: readKey()
        Terminal-->>AbstractPrompt: raw key bytes
        AbstractPrompt->>Component: update(normalizedKey)
        Component->>Input: handle(key, state)
        Input->>State: mutate values
        State-->>Component: watcher callbacks fire
        Component->>AbstractPrompt: [optionally] stop()
    end

    AbstractPrompt->>Component: resolve()
    Component-->>Caller: typed return value
    AbstractPrompt->>Component: destroy()
    AbstractPrompt->>Terminal: disableRaw()
```

---

## Class diagram — core types

```mermaid
classDiagram
    direction TB

    class IOInterface {
        <<interface>>
        +ask()
        +askConfirmation()
        +select()
        +write()
        +writeError()
    }

    class BaseIO {
        <<abstract>>
        +log()
        +emergency() warning() info() …
    }

    class ConsoleIO {
        -InputInterface input
        -OutputInterface output
        +enableDebugging()
    }

    class BufferIO {
        +getOutput() string
        +setUserInputs(inputs)
    }

    class NullIO

    IOInterface <|.. BaseIO
    BaseIO <|-- ConsoleIO
    BaseIO <|-- NullIO
    ConsoleIO <|-- BufferIO

    class ILifecycle {
        <<interface>>
        +mount()
        +render()
        +update(key)
        +destroy()
    }

    class IPromptComponent {
        <<interface>>
        +run() mixed
    }

    class AbstractPrompt {
        #running bool
        #context RenderContext
        #stop()
        #dispatch(event)
    }

    class Component {
        #state State
        #input Input
        #renderer Renderer
        #setup()*
        #resolve()*
    }

    ILifecycle <|.. AbstractPrompt
    IPromptComponent <|.. AbstractPrompt
    AbstractPrompt <|-- Component

    Component <|-- TextInput
    Component <|-- Password
    Component <|-- NumberInput
    Component <|-- Confirm
    Component <|-- Select
    Component <|-- MultiSelect
    Component <|-- Autocomplete
    Component <|-- DatePicker
```

---

## Reactive state flow

`State` is the single source of truth for every component. Bindings in `Input` mutate it; `watch()` callbacks fire synchronously after each mutation and may trigger re-renders.

```mermaid
flowchart LR
    KEY["Terminal::readKey()"]
    NORM["Key::normalize()"]
    INPUT["Input::handle()"]
    STATE["State\n(reactive store)"]
    WATCH["watch() callbacks"]
    CTX["RenderContext\n.markDirty()"]
    RENDER["Component::render()"]

    KEY --> NORM --> INPUT --> STATE
    STATE --> WATCH --> CTX --> RENDER
```

---

## Shell streaming model

`Shell::run()` avoids the classic pipe-deadlock problem by using `stream_select()` to drain stdout and stderr concurrently.

```mermaid
sequenceDiagram
    participant Shell
    participant proc_open
    participant stdout pipe
    participant stderr pipe
    participant tick callback

    Shell->>proc_open: open(command, pipes)
    loop Until feof(stdout) && feof(stderr)
        Shell->>stream_select: wait ≤50 ms
        stream_select-->>Shell: ready pipes
        Shell->>stdout pipe: fread(4096)
        Shell->>stderr pipe: fread(4096)
        Shell->>Shell: drain complete lines from buffers
        Shell->>tick callback: tick(lastLine, isStderr)
    end
    Shell->>proc_open: proc_close()
    Shell-->>Caller: ShellResult(exitCode, stdout[], stderr[])
```

---

## IO fallback strategy

`ConsoleIO` detects the terminal type and delegates accordingly:

```mermaid
flowchart TD
    CALL["ConsoleIO::ask() / select() / confirm()"]
    TTY{{"posix_isatty(STDIN) ?"}}
    REACTIVE["Reactive Component\n(raw mode, ANSI animation)"]
    SYMFONY["Symfony QuestionHelper\n(plain text, pipe-safe)"]

    CALL --> TTY
    TTY -- yes --> REACTIVE
    TTY -- no  --> SYMFONY
```

---

## Directory structure

```
src/
├── AbstractCommand.php      # Base for all commands
├── AbstractPrompt.php       # Reactive event loop engine
├── CLIApplication.php       # Dispatcher + built-in commands
├── Components/              # Interactive + display components
│   ├── Component.php        # Base: wires State, Input, Renderer
│   ├── TextInput.php
│   ├── Password.php
│   ├── NumberInput.php
│   ├── Confirm.php
│   ├── Select.php
│   ├── MultiSelect.php
│   ├── Autocomplete.php
│   ├── DatePicker.php
│   ├── Table.php
│   ├── Alert.php
│   ├── ProgressBar.php
│   └── SpinnerComponent.php
├── Depends/                 # Low-level primitives
│   ├── State.php            # Reactive key-value store
│   ├── Input.php            # Key binding dispatcher
│   ├── Terminal.php         # Raw mode, escape sequences
│   ├── Colors.php           # ANSI color / strip helper
│   ├── Renderer.php         # Scroll windowing, cursor mgmt
│   ├── RenderContext.php    # Per-frame metadata
│   ├── Shell.php            # proc_open streaming wrapper
│   ├── ShellResult.php      # Immutable result value object
│   ├── Fuzzy.php            # Fuzzy search + scoring
│   ├── Key.php              # Key constants + normalizer
│   ├── Spinner.php          # Frame-based spinner engine
│   └── SpinnerFrames.php    # Built-in frame sets
├── BaseIO.php               # PSR-3 bridge
├── ConsoleIO.php            # Real terminal IO
├── BufferIO.php             # In-memory IO (testing)
├── NullIO.php               # Silent IO (daemons)
├── Hooks.php                # Pub/sub event bus
├── IOInterface.php          # Unified I/O contract
├── ILifecycle.php           # Component lifecycle contract
├── IPromptComponent.php     # run() contract
├── IRenderer.php            # Renderer contract
└── Silencer.php             # PHP error suppression utility
```
