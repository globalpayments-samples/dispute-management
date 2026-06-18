FROM maven:3.9-eclipse-temurin-21

WORKDIR /app

COPY pom.xml ./
RUN mvn dependency:go-offline -q

COPY src ./src

RUN mvn clean package -DskipTests -q

EXPOSE 8000

CMD ["mvn", "cargo:run"]
