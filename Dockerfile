# ==========================================
# BUILDER MULTI-LENGUAJE
# ==========================================

FROM swift:6.0-bookworm AS swift-source
FROM golang:1.22-bookworm AS go-source

FROM debian:bookworm-slim AS multi-builder

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    curl wget gnupg ca-certificates \
    build-essential clang lldb lld nasm \
    libicu-dev libcurl4-openssl-dev libedit-dev libsqlite3-dev \
    libncurses-dev libpython3-dev libxml2-dev pkg-config uuid-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# Swift
COPY --from=swift-source /usr/bin/swift* /usr/bin/
COPY --from=swift-source /usr/lib/swift /usr/lib/swift
COPY --from=swift-source /usr/lib/libswift* /usr/lib/

# Go
COPY --from=go-source /usr/local/go /usr/local/go
ENV PATH="/usr/local/go/bin:${PATH}"

# Rust
RUN curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
ENV PATH="/root/.cargo/bin:${PATH}"

# .NET SDK
RUN wget https://dot.net/v1/dotnet-install.sh -O /tmp/dotnet-install.sh \
    && bash /tmp/dotnet-install.sh --channel 8.0 --install-dir /usr/share/dotnet \
    && ln -s /usr/share/dotnet/dotnet /usr/bin/dotnet \
    && rm /tmp/dotnet-install.sh

WORKDIR /app/services
COPY ./services .
RUN mkdir -p bin_outputs
